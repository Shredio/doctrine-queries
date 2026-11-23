<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use ArrayIterator;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Doctrine\ORM\Query\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\Exception\ToManyAssociationException;
use SortDirection;
use stdClass;
use Stringable;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\Symbol;
use Tests\Entity\Article;
use Tests\Entity\ArticleHashtag;
use Tests\Entity\Author;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\FavouritePrompt;
use Tests\Entity\FavouritePromptReference;
use Tests\Entity\OneToOneAssoc;
use Tests\Entity\OneToOnePrimary;
use Tests\Entity\OneToOnePrimaryReference;
use Tests\Entity\Prompt;
use Tests\Entity\TypedValues;
use Tests\TestCase;
use Tests\Unit\Helpers;

/**
 * Runs every criteria against all three query types and checks which articles match.
 */
final class CriteriaQueriesTest extends TestCase
{

	use DoctrineContext;
	use Helpers;

	/**
	 * @return iterable<string, array{array<string, mixed>, list<int>}>
	 */
	public static function provideCriteria(): iterable
	{
		yield 'no criteria' => [[], [1, 2, 3]];

		yield 'equals string' => [['title' => 'Sample Article'], [1]];
		yield 'equals integer' => [['id' => 2], [2]];
		yield 'equals numeric string' => [['id' => '2'], [2]];
		yield 'equals empty string' => [['title' => ''], []];
		yield 'explicit equals' => [['title =' => 'Sample Article'], [1]];
		yield 'not equals' => [['title !=' => 'Sample Article'], [2, 3]];
		yield 'not equals as <>' => [['title <>' => 'Sample Article'], [2, 3]];
		yield 'not equals non-existent value' => [['title !=' => 'Non-existent Article'], [1, 2, 3]];

		yield 'greater than' => [['id >' => 1], [2, 3]];
		yield 'greater than zero' => [['id >' => 0], [1, 2, 3]];
		yield 'greater than or equal' => [['id >=' => 2], [2, 3]];
		yield 'less than' => [['id <' => 3], [1, 2]];
		yield 'less than or equal' => [['id <=' => 2], [1, 2]];

		yield 'like' => [['title LIKE' => '%Article%'], [1, 2, 3]];
		yield 'like prefix' => [['title LIKE' => 'Third%'], [3]];
		yield 'like on another field' => [['content LIKE' => '%sample%'], [1]];
		yield 'lower case like' => [['title like' => 'Another%'], [2]];
		yield 'not like' => [['title NOT LIKE' => '%Sample%'], [2, 3]];

		yield 'is null' => [['symbol' => null], [2, 3]];
		yield 'is not null' => [['symbol !=' => null], [1]];
		yield 'is not null as <>' => [['symbol <>' => null], [1]];

		yield 'in integers' => [['id' => [1, 3]], [1, 3]];
		yield 'in strings' => [['title' => ['Sample Article', 'Third Article']], [1, 3]];
		yield 'in single item' => [['id' => [2]], [2]];
		yield 'in empty array' => [['id' => []], []];
		yield 'in traversable' => [['id' => new ArrayIterator(['first' => 1, 'second' => 2])], [1, 2]];
		yield 'not in' => [['id !=' => [1, 3]], [2]];
		yield 'not in empty array matches everything' => [['id !=' => []], [1, 2, 3]];

		yield 'range on the same field' => [['id >=' => 2, 'id <=' => 3], [2, 3]];
		yield 'in array with string keys' => [['id' => ['first' => 1, 'second' => 3]], [1, 3]];
		// SQL compares with NULL as unknown, a NULL in a list is compared by IS NULL instead.
		yield 'not equals skips null values' => [['symbol !=' => 'other'], [1]];
		yield 'in with a null item matches null values' => [['symbol' => ['sym', null]], [1, 2, 3]];
		yield 'in with only a null item matches null values' => [['symbol' => [null]], [2, 3]];
		yield 'not in with a null item skips null values' => [['symbol !=' => ['other', null]], [1]];
		yield 'not in with only a null item skips null values' => [['symbol !=' => [null]], [1]];
		yield 'in traversable with a null item matches null values' => [['symbol' => new ArrayIterator(['other', null])], [2, 3]];
		yield 'not in traversable skips null values' => [['symbol !=' => new ArrayIterator(['other'])], [1]];
		yield 'not in empty traversable matches null values' => [['symbol !=' => new ArrayIterator([])], [1, 2, 3]];
		yield 'backed enum' => [['type' => ArticleType::News], [1, 2, 3]];
		yield 'in backed enums' => [['type' => [ArticleType::News, ArticleType::Blog]], [1, 2, 3]];
		yield 'not in backed enums' => [['type !=' => [ArticleType::News]], []];
		yield 'backed enum value' => [['type' => 'blog'], []];
		yield 'datetime' => [['createdAt' => new DateTimeImmutable(self::FixtureCreatedAt)], [1, 2, 3]];
		// Doctrine converts a single object by its type, the objects in a list are converted by the type of the column.
		yield 'in datetimes' => [['createdAt' => [new DateTimeImmutable(self::FixtureCreatedAt), new DateTimeImmutable('2022-01-01')]], [1, 2, 3]];
		yield 'in mutable datetimes' => [['createdAt' => [new DateTime(self::FixtureCreatedAt)]], [1, 2, 3]];
		yield 'in datetimes as database values' => [['createdAt' => [self::FixtureCreatedAt]], [1, 2, 3]];
		yield 'not in datetimes' => [['createdAt !=' => [new DateTimeImmutable(self::FixtureCreatedAt)]], []];
		yield 'in custom type objects' => [['symbol' => [new Symbol('sym'), new Symbol('other')]], [1]];
		yield 'in custom type objects and database values' => [['symbol' => ['other', new Symbol('sym')]], [1]];
		yield 'not in custom type objects' => [['symbol !=' => [new Symbol('other')]], [1]];
		yield 'in traversable of custom type objects' => [['symbol' => new ArrayIterator([new Symbol('sym')])], [1]];
		yield 'datetime greater than' => [['createdAt >' => new DateTimeImmutable('2022-01-01')], []];
		yield 'custom type with DoctrineParameterAware' => [['symbol' => new Symbol('sym')], [1]];
		yield 'custom type as a database value' => [['symbol' => 'sym'], [1]];
		// The type of a string column passes an object through, it is bound by its text form as Doctrine binds it.
		yield 'stringable object' => [['title' => self::createStringable('Sample Article')], [1]];
		yield 'in stringable objects' => [['title' => [self::createStringable('Sample Article'), self::createStringable('Third Article')]], [1, 3]];

		yield 'association by identifier' => [['author' => 1], [1, 3]];
		yield 'association in' => [['author' => [2]], [2]];
		yield 'association not equals' => [['author !=' => 1], [2]];
		yield 'related field' => [['author.name' => 'Jane Smith'], [2]];
		yield 'related identifier' => [['author.id' => 1], [1, 3]];
		yield 'related field like' => [['author.name LIKE' => 'J%'], [1, 2, 3]];
		yield 'nested related field' => [['author.role.name' => 'Administrator'], [2]];
		yield 'nested association is null' => [['author.role' => null], [1, 3]];
		yield 'nested related identifier is null' => [['author.role.id' => null], [1, 3]];
		yield 'nested related identifier is not null' => [['author.role.id !=' => null], [2]];

		yield 'multiple criteria' => [['id >' => 1, 'title LIKE' => '%Article%'], [2, 3]];
		yield 'multiple criteria on root and relation' => [['author.name' => 'John Doe', 'symbol' => null], [3]];
		yield 'multiple criteria without a match' => [['id' => [1, 2], 'symbol' => null, 'title !=' => 'Non-existent Article'], [2]];
	}

	/**
	 * @return iterable<string, array{string, array<string, mixed>, list<int>}>
	 */
	public static function provideCriteriaForEachQueryType(): iterable
	{
		foreach (self::provideCriteria() as $name => [$criteria, $expectedIds]) {
			foreach (['objects', 'arrays', 'scalars'] as $queryType) {
				yield sprintf('%s (%s)', $name, $queryType) => [$queryType, $criteria, $expectedIds];
			}
		}
	}

	/**
	 * @param array<string, mixed> $criteria
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('provideCriteriaForEachQueryType')]
	public function testFindBy(string $queryType, array $criteria, array $expectedIds): void
	{
		$this->persistFixtures();

		$this->assertSame($expectedIds, $this->assertSingleQuery(fn (): array => $this->findIds($queryType, $criteria)));
	}

	/**
	 * @param array<string, mixed> $criteria
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('provideCriteria')]
	public function testCountAndExistsAgreeWithFindBy(array $criteria, array $expectedIds): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(count($expectedIds), $queries->countBy(Article::class, $criteria));
		$this->assertSame($expectedIds !== [], $queries->existsBy(Article::class, $criteria));
	}

	public function testEntityAsAssociationValue(): void
	{
		$this->persistFixtures();
		$em = $this->getEntityManager();
		$johnDoe = $em->getReference(Author::class, 1);
		$janeSmith = $em->getReference(Author::class, 2);

		foreach (['objects', 'arrays', 'scalars'] as $queryType) {
			$this->assertSame([1, 3], $this->findIds($queryType, ['author' => $johnDoe]), $queryType);
			$this->assertSame([2], $this->findIds($queryType, ['author !=' => $johnDoe]), $queryType);
			$this->assertSame([1, 2, 3], $this->findIds($queryType, ['author' => [$johnDoe, $janeSmith]]), $queryType);
			$this->assertSame([1, 3], $this->findIds($queryType, ['author !=' => [$janeSmith]]), $queryType);
		}

		$this->assertSame(2, $this->createDoctrineQueries()->countBy(Article::class, ['author' => $johnDoe]));
	}

	/**
	 * Doctrine rejects an entity without an identifier, it is not compared as a value object of the column.
	 */
	public function testEntityWithoutIdentifierIsRejected(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$unsavedAuthor = (new ReflectionClass(Author::class))->newInstanceWithoutConstructor();
		$message = 'Class "Tests\Entity\Author" does not have an identifier.';

		$calls = [
			'single' => static fn () => $queries->countBy(Article::class, ['author' => $unsavedAuthor]),
			'not equals' => static fn () => $queries->existsBy(Article::class, ['author !=' => $unsavedAuthor]),
			'list' => static fn () => $queries->scalars->findBy(Article::class, ['author' => [$unsavedAuthor]])->asArray(),
			'list after a saved entity' => fn () => $queries->countBy(Article::class, ['author' => [$this->getEntityManager()->getReference(Author::class, 1), $unsavedAuthor]]),
			'related entity' => static fn () => $queries->countBy(ArticleHashtag::class, ['article.author' => $unsavedAuthor]),
		];

		foreach ($calls as $name => $call) {
			try {
				$call();
				$this->fail(sprintf('Expected ORMInvalidArgumentException for %s.', $name));
			} catch (ORMInvalidArgumentException $exception) {
				$this->assertStringContainsString($message, $exception->getMessage(), $name);
			}
		}
	}

	/**
	 * The join column references the join column of the target entity, which has no type of its own. The value is left
	 * to Doctrine, which compares it with the identifier of the entity the target entity is identified by.
	 */
	public function testAssociationToEntityIdentifiedByAssociation(): void
	{
		$em = $this->getEntityManager();
		$em->persist($firstAssoc = new OneToOneAssoc(1, 'first'));
		$em->persist($secondAssoc = new OneToOneAssoc(2, 'second'));
		$em->flush();
		$em->persist($firstPrimary = new OneToOnePrimary($firstAssoc, 'first'));
		$em->persist($secondPrimary = new OneToOnePrimary($secondAssoc, 'second'));
		$em->persist(new OneToOnePrimaryReference(1, $firstPrimary));
		$em->persist(new OneToOnePrimaryReference(2, $firstPrimary));
		$em->persist(new OneToOnePrimaryReference(3, $secondPrimary));
		$em->flush();
		$queries = $this->createDoctrineQueries();
		$findIds = static fn (array $criteria): array => $queries->scalars
			->findColumnValuesBy(OneToOnePrimaryReference::class, 'id', $criteria, ['id' => SortDirection::Ascending])
			->asArray();

		$this->assertSame([1, 2], $findIds(['primary' => $firstPrimary]));
		$this->assertSame([3], $findIds(['primary !=' => $firstPrimary]));
		$this->assertSame([1, 2, 3], $findIds(['primary' => [$firstPrimary, $secondPrimary]]));
		$this->assertSame([1, 2], $findIds(['primary !=' => [$secondPrimary]]));
		$this->assertSame([3], $findIds(['primary' => $secondAssoc->id]));
		$this->assertSame([1, 2], $findIds(['primary' => [$firstAssoc->id]]));
		$this->assertSame([3], $findIds(['primary.assoc' => $secondAssoc]));
		$this->assertSame([1, 2], $findIds(['primary.assoc' => [$firstAssoc]]));
		$this->assertSame(2, $queries->countBy(OneToOnePrimary::class, ['assoc' => [$firstAssoc, $secondAssoc]]));
	}

	/**
	 * DQL cannot compare an association by a composite foreign key. Its value is left to Doctrine too, so the query fails
	 * by the error of Doctrine instead of the missing type of the column.
	 */
	public function testAssociationByCompositeForeignKeyIsRejectedByDoctrine(): void
	{
		$em = $this->getEntityManager();
		$em->persist($prompt = new Prompt(1, 'Prompt', 'Content'));
		$em->persist($favourite = new FavouritePrompt(7, $prompt));
		$em->persist(new FavouritePromptReference(1, $favourite));
		$em->flush();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(1, $queries->countBy(FavouritePromptReference::class, ['favourite.account' => [7, 8], 'favourite.prompt' => $prompt]));
		$this->assertSame(1, $queries->countBy(FavouritePrompt::class, ['prompt' => [$prompt], 'account' => 7]));

		foreach ([$favourite, [$favourite], 7, [7, 8]] as $index => $value) {
			try {
				$queries->countBy(FavouritePromptReference::class, ['favourite' => $value]);
				$this->fail(sprintf('Expected QueryException for value %d.', $index));
			} catch (QueryException $exception) {
				$this->assertStringContainsString('composite primary key is not supported', $exception->getMessage());
			}
		}
	}

	public function testListOfDatesIsConvertedByTheTypeOfTheColumn(): void
	{
		$em = $this->getEntityManager();
		$em->persist(self::createTypedValues(1, day: '2021-01-02'));
		$em->persist(self::createTypedValues(2, day: '2021-01-03'));
		$em->flush();
		$queries = $this->createDoctrineQueries();
		$afternoon = new DateTimeImmutable('2021-01-02 15:30:00');

		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(TypedValues::class, 'id', ['day' => [$afternoon]])->asArray(),
			'A date column is compared with the date only.',
		);
		$this->assertSame([2], $queries->scalars->findColumnValuesBy(TypedValues::class, 'id', ['day !=' => [$afternoon]])->asArray());
		$this->assertSame([1], $queries->scalars->findColumnValuesBy(TypedValues::class, 'id', ['moment' => [new DateTimeImmutable('2021-01-02 03:04:05')]])->asArray());
		$this->assertSame(1, $queries->deleteBy(TypedValues::class, ['day' => [$afternoon]]));
	}

	/**
	 * The days 2021-01-01, 2021-01-02 and 2021-01-03 compared with the afternoon of the second one.
	 *
	 * @return iterable<string, array{non-empty-string, list<int>}>
	 */
	public static function provideDateComparisons(): iterable
	{
		yield 'equals' => ['day', [2]];
		yield 'not equals' => ['day !=', [1, 3]];
		yield 'greater than or equal' => ['day >=', [2, 3]];
		yield 'greater than' => ['day >', [3]];
		yield 'less than or equal' => ['day <=', [1, 2]];
		yield 'less than' => ['day <', [1]];
	}

	/**
	 * @param non-empty-string $criteriaKey
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('provideDateComparisons')]
	public function testSingleDateIsConvertedByTheTypeOfTheColumn(string $criteriaKey, array $expectedIds): void
	{
		$em = $this->getEntityManager();
		$em->persist(self::createTypedValues(1, day: '2021-01-01'));
		$em->persist(self::createTypedValues(2, day: '2021-01-02'));
		$em->persist(self::createTypedValues(3, day: '2021-01-03'));
		$em->flush();
		$queries = $this->createDoctrineQueries();
		$findIds = static fn (mixed $value): array => $queries->scalars
			->findColumnValuesBy(TypedValues::class, 'id', [$criteriaKey => $value], ['id' => SortDirection::Ascending])
			->asArray();

		$this->assertSame($expectedIds, $findIds(new DateTimeImmutable('2021-01-02 15:30:00')), 'A date column is compared with the date only.');
		$this->assertSame($expectedIds, $findIds(new DateTime('2021-01-02 15:30:00')), 'A DateTime is converted for an immutable type too.');
		$this->assertSame($expectedIds, $findIds('2021-01-02'), 'As the database value.');
		$this->assertSame(count($expectedIds), $queries->countBy(TypedValues::class, [$criteriaKey => new DateTimeImmutable('2021-01-02 15:30:00')]));

		if ($criteriaKey === 'day' || $criteriaKey === 'day !=') {
			$this->assertSame($expectedIds, $findIds([new DateTimeImmutable('2021-01-02 15:30:00')]), 'As a list of the date.');
		}
	}

	public function testSingleDateComparedWithDateTimeAndTimeColumns(): void
	{
		$em = $this->getEntityManager();
		$em->persist(self::createTypedValues(1, day: '2021-01-01')); // the moment is 03:04:05 of the day
		$em->persist(self::createTypedValues(2, day: '2021-01-02'));
		$em->flush();
		$queries = $this->createDoctrineQueries();
		$findIds = static fn (array $criteria): array => $queries->scalars
			->findColumnValuesBy(TypedValues::class, 'id', $criteria, ['id' => SortDirection::Ascending])
			->asArray();

		$this->assertSame([2], $findIds(['moment >' => new DateTimeImmutable('2021-01-02 03:04:04')]), 'A datetime column keeps the time.');
		$this->assertSame([], $findIds(['moment >' => new DateTimeImmutable('2021-01-02 03:04:05')]));
		$this->assertSame([1], $findIds(['moment <' => new DateTime('2021-01-02 03:04:05')]));
		$this->assertSame([2], $findIds(['moment' => new DateTimeImmutable('2021-01-02 03:04:05')]));
		$this->assertSame([1, 2], $findIds(['time' => new DateTimeImmutable('2030-05-06 03:04:05')]), 'A time column is compared with the time only.');
		$this->assertSame([], $findIds(['time >' => new DateTime('2000-01-01 03:04:05')]));
		$this->assertSame([1, 2], $findIds(['time >=' => new DateTime('2000-01-01 03:04:05')]));
	}

	public function testListOfUnsupportedObjectsIsRejected(): void
	{
		$this->persistFixtures();

		$this->expectException(ConversionException::class);

		$this->createDoctrineQueries()->scalars->findBy(Article::class, ['createdAt' => [new Symbol('sym')]])->asArray();
	}

	public function testToManyAssociationIsRejected(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$message = 'Field "hashTags" of entity "Tests\\Entity\\Article" is a to-many association, use a field of the related entity instead, e.g. "hashTags.<field>".';

		$calls = [
			'criteria' => static fn () => $queries->scalars->findBy(Article::class, ['hashTags' => 1]),
			'select' => static fn () => $queries->arrays->findBy(Article::class, select: ['id', 'hashTags']),
			'order' => static fn () => $queries->objects->findBy(Article::class, orderBy: ['hashTags' => SortDirection::Ascending]),
			'column values' => static fn () => $queries->scalars->findColumnValuesBy(Article::class, 'hashTags'),
			'count' => static fn () => $queries->countBy(Article::class, field: 'hashTags'),
			'delete' => static fn () => $queries->deleteBy(Article::class, ['hashTags' => 1]),
		];

		foreach ($calls as $name => $call) {
			try {
				$call();
				$this->fail(sprintf('Expected ToManyAssociationException for %s.', $name));
			} catch (ToManyAssociationException $exception) {
				$this->assertSame($message, $exception->getMessage(), $name);
			}
		}

		$nested = static fn () => $queries->scalars->findBy(ArticleHashtag::class, ['article.hashTags' => 1]);
		try {
			$nested();
			$this->fail('Expected ToManyAssociationException for a nested path.');
		} catch (ToManyAssociationException $exception) {
			$this->assertStringContainsString('Field "article.hashTags" of entity "Tests\\Entity\\ArticleHashtag"', $exception->getMessage());
		}
	}

	public function testClassThatIsNotAnEntityIsRejected(): void
	{
		$queries = $this->createDoctrineQueries();

		$calls = [
			'objects' => static fn () => $queries->objects->findBy(stdClass::class),
			'scalars' => static fn () => $queries->scalars->findBy(stdClass::class),
			'count' => static fn () => $queries->countBy(stdClass::class),
			'exists' => static fn () => $queries->existsBy(stdClass::class, []),
			'exists many' => static fn () => $queries->existsManyBy(stdClass::class, [['id' => 1]]),
			'delete' => static fn () => $queries->deleteBy(stdClass::class),
			'last id' => static fn () => $queries->getLastId(stdClass::class),
		];

		foreach ($calls as $name => $call) {
			try {
				$call();
				$this->fail(sprintf('Expected NoClassMetadataException for %s.', $name));
			} catch (NoClassMetadataException $exception) {
				$this->assertSame('Class "stdClass" is not an entity managed by Doctrine.', $exception->getMessage(), $name);
			}
		}
	}

	public function testUnknownFieldIsRejectedBeforeReachingDql(): void
	{
		$this->persistFixtures();

		$this->expectException(FieldNotExistsException::class);
		$this->expectExceptionMessage('Field "unknown" does not exist in entity "Tests\Entity\Article".');

		$this->createDoctrineQueries()->scalars->findBy(Article::class, ['unknown' => 1])->asArray();
	}

	public function testFieldUsedAsAssociationIsRejected(): void
	{
		$this->persistFixtures();

		$this->expectException(InvalidAssociationPathException::class);

		$this->createDoctrineQueries()->scalars->findBy(Article::class, ['title.length >' => 1])->asArray();
	}

	public function testUnknownOrderByFieldIsRejected(): void
	{
		$this->persistFixtures();

		$this->expectException(FieldNotExistsException::class);

		$this->createDoctrineQueries()->scalars->findBy(Article::class, orderBy: ['unknown' => SortDirection::Ascending])->asArray();
	}

	private static function createStringable(string $text): Stringable
	{
		return new readonly class ($text) implements Stringable {

			public function __construct(
				private string $text,
			)
			{
			}

			public function __toString(): string
			{
				return $this->text;
			}

		};
	}

	/**
	 * @param array<string, mixed> $criteria
	 * @return list<int>
	 */
	private function findIds(string $queryType, array $criteria): array
	{
		$queries = $this->createDoctrineQueries();
		$orderBy = ['id' => SortDirection::Ascending];

		return match ($queryType) {
			'objects' => array_map(
				static fn (Article $article): int => $article->getId(),
				$queries->objects->findBy(Article::class, $criteria, $orderBy)->asArray(),
			),
			'arrays' => $this->extractIds($queries->arrays->findBy(Article::class, $criteria, $orderBy)->asArray()),
			'scalars' => $this->extractIds($queries->scalars->findBy(Article::class, $criteria, $orderBy)->asArray()),
		};
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<int>
	 */
	private function extractIds(array $rows): array
	{
		return array_map(static function (array $row): int {
			self::assertIsInt($row['id']);

			return $row['id'];
		}, $rows);
	}

}
