<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use Doctrine\ORM\Tools\SchemaTool;
use PDO;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidResultKeyException;
use Shredio\DoctrineQueries\Exception\MultipleFieldSelectionException;
use Shredio\DoctrineQueries\Exception\ToManyAssociationException;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\Symbol;
use Tests\Doctrine\TestManagerRegistry;
use Tests\Entity\Article;
use Tests\Entity\ArticleHashtag;
use Tests\Entity\Author;
use Tests\Entity\FavouritePrompt;
use Tests\Entity\Prompt;
use Tests\Entity\Role;
use Tests\Factory\EntityManagerFactory;
use Tests\TestCase;

/**
 * countGroupedBy() of DoctrineQueries.
 */
final class CountGroupedByTest extends TestCase
{

	use DoctrineContext;

	public function testCountsEntitiesForEachAssociation(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$counts = $this->assertSingleQuery(static fn (): array => $queries->countGroupedBy(Article::class, 'author')->asArray());

		$this->assertSame([1 => 2, 2 => 1], self::sortedByKey($counts), 'The keys are the identifiers of the authors.');
	}

	public function testCountsEntitiesForEachFieldValue(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			['Jane Smith' => 1, 'John Doe' => 2],
			self::sortedByKey($queries->countGroupedBy(Article::class, 'author.name')->asArray()),
		);
		$this->assertSame(
			['2021-01-01 00:00:00' => 3],
			$queries->countGroupedBy(Article::class, 'createdAt')->asArray(),
			'The keys are database values, a date is not converted to an object.',
		);
		$this->assertSame(['news' => 3], $queries->countGroupedBy(Article::class, 'type')->asArray(), 'An enum is returned as its value.');
	}

	public function testCriteriaFilterTheCountedEntities(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame([1 => 1, 2 => 1], self::sortedByKey($queries->countGroupedBy(Article::class, 'author', ['id' => [1, 2]])->asArray()));
		$this->assertSame([2 => 1], $queries->countGroupedBy(Article::class, 'author', ['author.role.name' => 'Administrator'])->asArray());
	}

	public function testValueWithoutEntityIsMissing(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$counts = $queries->countGroupedBy(Article::class, 'author', ['author' => [1, 999]])->asArray();

		$this->assertSame([1 => 2], $counts, 'No entity has the author 999, so it has no count, not even 0.');
		$this->assertSame(0, $counts[999] ?? 0);
		$this->assertSame([], $queries->countGroupedBy(Article::class, 'author', ['id' => 0])->asArray());
		$this->assertSame([], $queries->countGroupedBy(Article::class, 'author', ['author' => []])->asArray());
	}

	public function testCountsValuesOfField(): void
	{
		$this->persistFixtures(static fn (Author $johnDoe): Article => new Article(4, 'Fourth Article', 'This is the fourth article.', $johnDoe, new Symbol('sym')));
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			['Jane Smith' => 0, 'John Doe' => 2],
			self::sortedByKey($queries->countGroupedBy(Article::class, 'author.name', field: 'symbol')->asArray()),
			'COUNT() of a field skips NULL values, the only article of Jane Smith has no symbol.',
		);
		$this->assertSame(['sym' => 2], $queries->countGroupedBy(Article::class, 'symbol', ['symbol !=' => null], 'author')->asArray());
		$this->assertSame(['sym' => 1], $queries->countGroupedBy(Article::class, 'symbol', ['symbol !=' => null], 'author', distinct: true)->asArray());
	}

	public function testNullKey(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$pairs = [];
		foreach ($queries->countGroupedBy(Article::class, 'symbol')->yield() as $symbol => $count) {
			$pairs[] = [$symbol, $count];
		}
		usort($pairs, static fn (array $first, array $second): int => $first[1] <=> $second[1]);

		$this->assertSame([['sym', 1], [null, 2]], $pairs, 'yield() returns the articles without a symbol under the NULL key.');
		$this->assertSame(['sym' => 1], $queries->countGroupedBy(Article::class, 'symbol', ['symbol !=' => null])->asArray());

		$this->expectException(InvalidResultKeyException::class);

		$queries->countGroupedBy(Article::class, 'symbol')->asArray();
	}

	public function testJoinConfig(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			['Administrator' => 1],
			$queries->countGroupedBy(Article::class, 'author.role.name', joinConfig: 'inner')->asArray(),
			'The inner join leaves out the articles of the authors without a role.',
		);
		$this->assertSame(
			['Administrator' => 1],
			$queries->countGroupedBy(Article::class, 'author.role.name', joinConfig: ['author.role' => 'inner'])->asArray(),
		);
		$this->assertSame(
			[[null, 2], ['Administrator', 1]],
			self::sortedPairs($queries->countGroupedBy(Article::class, 'author.role.name')->yield()),
			'The left join keeps them under the NULL key.',
		);
	}

	public function testKeyThroughToManyAssociation(): void
	{
		$this->persistFixtures();
		$this->persistHashtags();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			['doctrine' => 1, 'php' => 2, 'symfony' => 1],
			self::sortedByKey($queries->countGroupedBy(Article::class, 'hashTags.hashtag')->asArray()),
			'Articles are counted for each of their hashtags.',
		);
		$this->assertSame(
			['doctrine' => 1, 'php' => 2],
			self::sortedByKey($queries->countGroupedBy(Article::class, 'hashTags.hashtag', ['hashTags.hashtag' => ['php', 'doctrine']])->asArray()),
			'Criteria on the association of the key filter its rows.',
		);
	}

	public function testEntityWithoutToManyRelatedEntity(): void
	{
		$this->persistFixtures(static fn (Author $johnDoe): Article => new Article(4, 'Fourth Article', 'This is the fourth article.', $johnDoe));
		$this->persistHashtags();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			[[null, 1], ['php', 1]],
			self::sortedPairs($queries->countGroupedBy(Article::class, 'hashTags.hashtag', ['id' => [2, 4]])->yield()),
			'The left join keeps the article without a hashtag under the NULL key.',
		);
		$this->assertSame(
			['php' => 1],
			$queries->countGroupedBy(Article::class, 'hashTags.hashtag', ['id' => [2, 4]], joinConfig: 'inner')->asArray(),
		);
	}

	public function testCriteriaOnToManyAssociationDoNotMultiplyTheCounts(): void
	{
		$this->persistFixtures();
		$this->persistHashtags();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			[1 => 1, 2 => 1],
			self::sortedByKey($queries->countGroupedBy(Article::class, 'author', ['hashTags.hashtag' => ['php', 'doctrine']])->asArray()),
			'The first article matches two hashtags and is counted once.',
		);
		$this->assertSame(
			[1 => 2, 2 => 1],
			self::sortedByKey($queries->countGroupedBy(Article::class, 'author', ['hashTags.hashtag' => ['php', 'doctrine']], 'hashTags.hashtag')->asArray()),
			'Counting a field of the hashtags counts the matching hashtags.',
		);
	}

	public function testCompositeIdentifier(): void
	{
		$em = $this->getEntityManager();
		$em->persist($first = new Prompt(1, 'First', 'First prompt'));
		$em->persist($second = new Prompt(2, 'Second', 'Second prompt'));
		$em->persist(new FavouritePrompt(10, $first));
		$em->persist(new FavouritePrompt(10, $second));
		$em->persist(new FavouritePrompt(20, $first));
		$em->flush();
		$em->clear();
		$queries = $this->createDoctrineQueries();

		$this->assertSame([10 => 2, 20 => 1], self::sortedByKey($queries->countGroupedBy(FavouritePrompt::class, 'account')->asArray()));
		$this->assertSame([1 => 2, 2 => 1], self::sortedByKey($queries->countGroupedBy(FavouritePrompt::class, 'prompt')->asArray()));
	}

	public function testLimit(): void
	{
		$this->persistFixtures();

		$this->assertCount(1, $this->createDoctrineQueries()->countGroupedBy(Article::class, 'author')->setLimit(1)->asArray());
	}

	public function testCountsAreIntegersWithStringifiedFetches(): void
	{
		$em = EntityManagerFactory::createInMemory([PDO::ATTR_STRINGIFY_FETCHES => true]);
		(new SchemaTool($em))->createSchema([$em->getClassMetadata(Role::class)]);
		$em->persist(new Role(7, 'Seventh'));
		$em->persist(new Role(8, 'Seventh'));
		$em->flush();

		$queries = new DoctrineQueries(new TestManagerRegistry($em));

		$this->assertSame(['7'], $queries->scalars->findColumnValuesBy(Role::class, 'id', ['id' => 7])->asArray(), 'The driver returns integers as strings.');
		$this->assertSame(['Seventh' => 2], $queries->countGroupedBy(Role::class, 'name')->asArray());
		$this->assertSame([7 => 1, 8 => 1], self::sortedByKey($queries->countGroupedBy(Role::class, 'id')->asArray()));

		$em->getConnection()->close();
	}

	public function testRejectsWildcardKey(): void
	{
		$this->persistFixtures();

		$this->expectException(MultipleFieldSelectionException::class);

		$this->createDoctrineQueries()->countGroupedBy(Article::class, 'author.*');
	}

	public function testRejectsUnknownKey(): void
	{
		$this->persistFixtures();

		$this->expectException(FieldNotExistsException::class);

		$this->createDoctrineQueries()->countGroupedBy(Article::class, 'unknown');
	}

	public function testRejectsToManyAssociationAsKey(): void
	{
		$this->persistFixtures();

		$this->expectException(ToManyAssociationException::class);

		$this->createDoctrineQueries()->countGroupedBy(Article::class, 'hashTags');
	}

	private function persistHashtags(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'php'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'doctrine'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 2), 'php'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 3), 'symfony'));
		$em->flush();
		$em->clear();
	}

	/**
	 * The database returns the groups in no particular order.
	 *
	 * @template TValue
	 * @param array<array-key, TValue> $values
	 * @return array<array-key, TValue>
	 */
	private static function sortedByKey(array $values): array
	{
		ksort($values);

		return $values;
	}

	/**
	 * @param iterable<mixed, mixed> $pairs
	 * @return list<array{mixed, mixed}> The pairs ordered by the key, NULL first
	 */
	private static function sortedPairs(iterable $pairs): array
	{
		$sorted = [];
		foreach ($pairs as $key => $value) {
			$sorted[] = [$key, $value];
		}
		usort($sorted, static fn (array $first, array $second): int => $first[0] <=> $second[0]);

		return $sorted;
	}

}
