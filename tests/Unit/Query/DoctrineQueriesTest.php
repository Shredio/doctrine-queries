<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use Doctrine\ORM\Tools\SchemaTool;
use Generator;
use InvalidArgumentException;
use LogicException;
use PDO;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\TestManagerRegistry;
use Tests\Entity\Article;
use Tests\Entity\Country;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\FavouritePrompt;
use Tests\Entity\Prompt;
use Tests\Entity\Role;
use Tests\Entity\TypedValues;
use Tests\Factory\EntityManagerFactory;
use Tests\TestCase;
use Tests\Unit\Helpers;

/**
 * existsManyBy() and getLastId() of DoctrineQueries.
 */
final class DoctrineQueriesTest extends TestCase
{

	use DoctrineContext;
	use Helpers;

	public function testExistsManyBy(): void
	{
		$this->persistFixtures();

		$results = $this->assertSingleQuery(fn () => $this->createDoctrineQueries()->existsManyBy(Article::class, [
			['id' => 1],
			['id' => 999],
			['id' => 2],
		]));

		$this->assertTrue($results->has(['id' => 1]));
		$this->assertTrue($results->has(['id' => 2]));
		$this->assertFalse($results->has(['id' => 999]));
		$this->assertFalse($results->has(['id' => 3]), 'Rows that were not asked for are not loaded.');
		$this->assertTrue($results->has(['id' => '1']), 'Values are compared the same way as in the database.');
	}

	public function testExistsManyByNothingExists(): void
	{
		$this->persistFixtures();

		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, [['id' => 999], ['id' => 1000]]);

		$this->assertFalse($results->has(['id' => 999]));
		$this->assertFalse($results->has(['id' => 1000]));
	}

	public function testExistsManyByWithoutValuesDoesNotQuery(): void
	{
		$results = $this->assertQueryCount(0, fn () => $this->createDoctrineQueries()->existsManyBy(Article::class, []));

		$this->assertFalse($results->has(['id' => 1]));
	}

	public function testExistsManyByMultipleFields(): void
	{
		$this->persistFixtures();

		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, [
			['id' => 1, 'author' => 1],
			['id' => 2, 'author' => 2],
			['id' => 1, 'author' => 999],
		]);

		$this->assertTrue($results->has(['id' => 1, 'author' => 1]));
		$this->assertTrue($results->has(['author' => 2, 'id' => 2]), 'The order of the keys does not matter.');
		$this->assertFalse($results->has(['id' => 1, 'author' => 999]));
		$this->assertFalse($results->has(['id' => 2, 'author' => 1]));
	}

	public function testExistsManyByNullValues(): void
	{
		$this->persistFixtures();

		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, [
			['id' => 1, 'symbol' => null],
			['id' => 2, 'symbol' => null],
			['id' => 1, 'symbol' => 'sym'],
		]);

		$this->assertTrue($results->has(['id' => 2, 'symbol' => null]));
		$this->assertTrue($results->has(['id' => 1, 'symbol' => 'sym']));
		$this->assertFalse($results->has(['id' => 1, 'symbol' => null]));
		$this->assertFalse($results->has(['id' => 2, 'symbol' => '']), 'An empty string is not NULL.');
	}

	public function testExistsManyByNullValueBeforeAnotherField(): void
	{
		$this->persistFixtures();

		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, [
			['symbol' => null, 'id' => 2],
			['symbol' => null, 'id' => 1],
		]);

		$this->assertTrue($results->has(['symbol' => null, 'id' => 2]));
		$this->assertFalse($results->has(['symbol' => null, 'id' => 1]), 'The first article has a symbol.');
		$this->assertFalse($results->has(['symbol' => null, 'id' => 3]), 'The fields after a NULL value still restrict the rows.');
	}

	public function testExistsManyByRowsWithDifferentFields(): void
	{
		$this->persistFixtures();

		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, [
			['id' => 1],
			['title' => 'Third Article'],
		]);

		$this->assertTrue($results->has(['id' => 1, 'title' => 'Sample Article']));
		$this->assertTrue($results->has(['id' => 3, 'title' => 'Third Article']));
		$this->assertFalse($results->has(['id' => 2, 'title' => 'Another Article']));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Missing key "title" in values array.');

		$results->has(['id' => 1]); // every field used by any of the rows is required
	}

	public function testExistsManyByIgnoresEmptyRows(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$results = $queries->existsManyBy(Article::class, [[], ['id' => 1], [], ['id' => 3]]);

		$this->assertTrue($results->has(['id' => 1]));
		$this->assertTrue($results->has(['id' => 3]), 'The rows after an empty one are still checked.');
		$this->assertFalse($results->has(['id' => 2]), 'An empty row does not match every article.');

		$this->assertQueryCount(0, static fn () => $queries->existsManyBy(Article::class, [[], []]));
	}

	public function testExistsManyByCompositeIdentifier(): void
	{
		$em = $this->getEntityManager();
		$em->persist($first = new Prompt(1, 'First', 'First prompt'));
		$em->persist($second = new Prompt(2, 'Second', 'Second prompt'));
		$em->persist(new FavouritePrompt(10, $first));
		$em->persist(new FavouritePrompt(20, $second));
		$em->flush();
		$em->clear();

		$results = $this->createDoctrineQueries()->existsManyBy(FavouritePrompt::class, [
			['account' => 10, 'prompt' => 1],
			['account' => 10, 'prompt' => 2],
			['account' => 20, 'prompt' => 2],
		]);

		$this->assertTrue($results->has(['account' => 10, 'prompt' => 1]));
		$this->assertFalse($results->has(['account' => 10, 'prompt' => 2]));
		$this->assertTrue($results->has(['account' => 20, 'prompt' => 2]));
	}

	public function testExistsManyByRejectsRelatedFields(): void
	{
		$this->persistFixtures();

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('Cannot check the existence of Tests\Entity\Article by "author.name", existsManyBy() does not join related entities.');

		$this->createDoctrineQueries()->existsManyBy(Article::class, [['author.name' => 'John Doe']]);
	}

	public function testExistsManyByComparesDatabaseValues(): void
	{
		$this->persistFixtures();

		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, [
			['id' => 1, 'type' => ArticleType::News, 'createdAt' => self::FixtureCreatedAt, 'symbol' => 'sym'],
			['id' => 2, 'type' => 'blog', 'createdAt' => self::FixtureCreatedAt, 'symbol' => null],
		]);

		$this->assertTrue($results->has(['id' => 1, 'type' => 'news', 'createdAt' => self::FixtureCreatedAt, 'symbol' => 'sym']));
		$this->assertTrue($results->has(['id' => 1, 'type' => ArticleType::News, 'createdAt' => self::FixtureCreatedAt, 'symbol' => 'sym']), 'Backed enums are compared by their value.');
		$this->assertFalse($results->has(['id' => 2, 'type' => 'blog', 'createdAt' => self::FixtureCreatedAt, 'symbol' => null]));
	}

	/**
	 * A decimal comes back as '12.50' from MySQL but as 12.5 from SQLite, has() has to be asked the same way.
	 */
	public function testExistsManyByComparesDecimalsAsTheDriverReturnsThem(): void
	{
		$em = $this->getEntityManager();
		$em->persist(self::createTypedValues(1, price: '12.50'));
		$em->flush();
		$queries = $this->createDoctrineQueries();

		$returnedPrice = $queries->scalars->findSingleColumnValueBy(TypedValues::class, 'price', ['id' => 1]);
		$this->assertTrue(is_string($returnedPrice) || is_int($returnedPrice) || is_float($returnedPrice));

		$results = $queries->existsManyBy(TypedValues::class, [['id' => 1, 'price' => '12.50']]);

		$this->assertTrue($results->has(['id' => 1, 'price' => $returnedPrice]));
		$this->assertSame((string) $returnedPrice === '12.50', $results->has(['id' => 1, 'price' => '12.50']));
		$this->assertSame((string) $returnedPrice === '12.5', $results->has(['id' => 1, 'price' => '12.5']));
		$this->assertFalse($results->has(['id' => 1, 'price' => '12.51']));
	}

	public function testExistsManyByAcceptsIterables(): void
	{
		$this->persistFixtures();

		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, self::generateValues());

		$this->assertTrue($results->has(['id' => 3]));
		$this->assertFalse($results->has(['id' => 4]));
	}

	public function testExistsManyByHasRequiresAllKeys(): void
	{
		$this->persistFixtures();
		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, [['id' => 1, 'author' => 1]]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Missing key "author" in values array.');

		$results->has(['id' => 1]);
	}

	public function testExistsManyByRejectsUnknownFields(): void
	{
		$this->persistFixtures();

		$this->expectException(FieldNotExistsException::class);

		$this->createDoctrineQueries()->existsManyBy(Article::class, [['id = 1 OR 1' => 1]]);
	}

	public function testExistsManyBySingleFieldUsesListParameter(): void
	{
		$this->persistFixtures();
		$getQueries = $this->captureQueries();

		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, [
			['id' => 1],
			['id' => 999],
			['id' => 2],
		]);

		$queries = $getQueries();
		$this->assertCount(1, $queries);
		$this->assertStringContainsString('IN (?, ?, ?)', $queries[0]);
		$this->assertTrue($results->has(['id' => 1]));
		$this->assertTrue($results->has(['id' => 2]));
		$this->assertFalse($results->has(['id' => 999]));
	}

	public function testExistsManyBySingleFieldWithNull(): void
	{
		$this->persistFixtures();

		$results = $this->assertQueryCount(2, fn () => $this->createDoctrineQueries()->existsManyBy(Article::class, [
			['symbol' => 'sym'],
			['symbol' => null],
			['symbol' => 'missing'],
		]), 'NULL is checked by a query of its own, "IN (NULL)" never matches.');

		$this->assertTrue($results->has(['symbol' => 'sym']));
		$this->assertTrue($results->has(['symbol' => null]));
		$this->assertFalse($results->has(['symbol' => 'missing']));
	}

	public function testExistsManyBySingleFieldOfMixedTypesBindsEachValue(): void
	{
		$this->persistFixtures();
		$getQueries = $this->captureQueries();

		$results = $this->createDoctrineQueries()->existsManyBy(Article::class, [
			['id' => 1],
			['id' => '2'],
			['id' => 999],
		]);

		$queries = $getQueries();
		$this->assertCount(1, $queries);
		$this->assertStringNotContainsString(' IN ', $queries[0], 'A list parameter has a single type for all of its values.');
		$this->assertTrue($results->has(['id' => 1]));
		$this->assertTrue($results->has(['id' => 2]));
		$this->assertFalse($results->has(['id' => 999]));
	}

	public function testExistsManyBySingleFieldInChunks(): void
	{
		$this->persistFixtures();
		$values = array_map(static fn (int $id): array => ['id' => $id], range(1, 1200));

		$results = $this->assertQueryCount(3, fn () => $this->createDoctrineQueries()->existsManyBy(Article::class, $values));

		$this->assertTrue($results->has(['id' => 1]));
		$this->assertTrue($results->has(['id' => 3]));
		$this->assertFalse($results->has(['id' => 1200]));
	}

	public function testExistsManyByMultipleFieldsInChunks(): void
	{
		$this->persistFixtures();

		$results = $this->assertQueryCount(
			3,
			fn () => $this->createDoctrineQueries()->existsManyBy(Article::class, self::createValuesForChunks()),
			'2 fields per row are queried in chunks of 250 rows.',
		);

		$this->assertTrue($results->has(['id' => 1, 'author' => 1]), 'Found in the first chunk.');
		$this->assertTrue($results->has(['id' => 2, 'author' => 2]), 'Found in the second chunk.');
		$this->assertTrue($results->has(['id' => 3, 'author' => 1]), 'Found in the last chunk.');
		$this->assertFalse($results->has(['id' => 1000, 'author' => 1]));
	}

	public function testExistsManyByChunksOfTheSameSizeHaveTheSameDql(): void
	{
		$queryBuilders = $this->createQueryBuilderFactory()->createExistsManyBy(Article::class, self::createValuesForChunks());

		$this->assertCount(3, $queryBuilders);
		$this->assertSame($queryBuilders[0]->getDQL(), $queryBuilders[1]->getDQL(), 'The parsed DQL is reused from the query cache.');
		$this->assertNotSame($queryBuilders[0]->getDQL(), $queryBuilders[2]->getDQL(), 'The last chunk is smaller.');
	}

	public function testExistsManyByIgnoresEmptyValueSets(): void
	{
		$this->persistFixtures();

		$results = $this->assertQueryCount(0, fn () => $this->createDoctrineQueries()->existsManyBy(Article::class, [[], []]));

		$this->assertFalse($results->has(['id' => 1]));
	}

	public function testGetLastId(): void
	{
		$this->persistFixtures();

		$this->assertSame(3, $this->assertSingleQuery(fn (): ?int => $this->createDoctrineQueries()->getLastId(Article::class)));
	}

	public function testGetLastIdWithoutEntities(): void
	{
		$this->assertNull($this->createDoctrineQueries()->getLastId(Article::class));
	}

	public function testGetLastIdOfTheFirstEntity(): void
	{
		$this->persistFixtures();

		$this->assertSame(1, $this->createDoctrineQueries()->getLastId(Role::class), 'The fixtures contain a single role.');
	}

	public function testGetLastIdRejectsNonPositiveIdentifier(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Role(0, 'Zero'));
		$em->flush();

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('Entity Tests\Entity\Role has invalid ID 0.');

		$this->createDoctrineQueries()->getLastId(Role::class);
	}

	public function testGetLastIdOfNonIntegerIdentifier(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new Country('CZ', 'Czechia'));
		$em->flush();

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('Entity Tests\Entity\Country has non-integer ID.');

		$this->createDoctrineQueries()->getLastId(Country::class);
	}

	public function testGetLastIdWithStringifiedFetches(): void
	{
		$em = EntityManagerFactory::createInMemory([PDO::ATTR_STRINGIFY_FETCHES => true]);
		(new SchemaTool($em))->createSchema([$em->getClassMetadata(Role::class)]);
		$em->persist(new Role(7, 'Seventh'));
		$em->flush();

		$queries = new DoctrineQueries(new TestManagerRegistry($em));

		$this->assertSame('7', $queries->scalars->findSingleColumnValueBy(Role::class, 'id', []), 'The driver returns integers as strings.');
		$this->assertSame(7, $queries->getLastId(Role::class));

		$em->getConnection()->close();
	}

	public function testGetLastIdOfCompositeIdentifier(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('Entity Tests\Entity\FavouritePrompt has composite or no ID.');

		$this->createDoctrineQueries()->getLastId(FavouritePrompt::class);
	}

	/**
	 * 600 rows of 2 fields, the existing articles are in the first, the second and the last chunk of 250 rows.
	 *
	 * @return list<array{id: int, author: int}>
	 */
	private static function createValuesForChunks(): array
	{
		$values = array_map(static fn (int $id): array => ['id' => $id, 'author' => 1], range(1000, 1599));
		$values[0] = ['id' => 1, 'author' => 1];
		$values[300] = ['id' => 2, 'author' => 2];
		$values[599] = ['id' => 3, 'author' => 1];

		return $values;
	}

	/**
	 * @return Generator<int, array{id: int}>
	 */
	private static function generateValues(): Generator
	{
		yield ['id' => 3];
		yield ['id' => 4];
	}

}
