<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use ArrayIterator;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Criteria\AnyOf;
use Shredio\DoctrineQueries\Criteria\Criteria;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\ToManyAssociationException;
use Shredio\DoctrineQueries\Pagination\Pagination;
use Shredio\DoctrineQueries\Select\QueryType;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\Symbol;
use Tests\Entity\Article;
use Tests\Entity\ArticleHashtag;
use Tests\Entity\Author;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\Note;
use Tests\Entity\NoteTag;
use Tests\Entity\ProjectMember;
use Tests\Entity\ProjectRole;
use Tests\Entity\Role;
use Tests\TestCase;
use Tests\Unit\Helpers;
use Throwable;

/**
 * Criteria::anyOf() matches the rows that match at least one of its alternatives.
 *
 * Hashtags created by createQueriesWithHashtags():
 *
 * | Article | hashtags      |
 * |---------|---------------|
 * | 1       | php, doctrine |
 * | 2       | php           |
 * | 3       | -             |
 */
final class AnyOfCriteriaTest extends TestCase
{

	use DoctrineContext;
	use Helpers;

	private const array OrderById = ['id' => SortDirection::Ascending];

	/**
	 * @return iterable<string, array{array<array-key, mixed>, list<int>}>
	 */
	public static function provideCriteria(): iterable
	{
		yield 'two alternatives' => [[Criteria::anyOf(['id' => 1], ['id' => 3])], [1, 3]];
		yield 'single alternative' => [[Criteria::anyOf(['id' => 2])], [2]];
		yield 'single alternative with more criteria' => [[Criteria::anyOf(['id >' => 1, 'symbol' => null])], [2, 3]];
		yield 'alternatives of different fields' => [[Criteria::anyOf(['title' => 'Sample Article'], ['id' => 3])], [1, 3]];
		yield 'three alternatives' => [[Criteria::anyOf(['id' => 3], ['id' => 1], ['id' => 2])], [1, 2, 3]];
		yield 'criteria of an alternative all have to match' => [[Criteria::anyOf(['id' => 1, 'title' => 'Third Article'], ['id' => 2])], [2]];
		yield 'alternative with more criteria' => [[Criteria::anyOf(['id >' => 1, 'symbol' => null, 'title LIKE' => 'Third%'], ['id' => 1])], [1, 3]];
		yield 'no alternative matches' => [[Criteria::anyOf(['id' => 10], ['title' => 'Missing Article'])], []];
		yield 'every alternative matches the same row' => [[Criteria::anyOf(['id' => 1], ['title' => 'Sample Article'], ['symbol !=' => null])], [1]];
		yield 'operators' => [[Criteria::anyOf(['id <' => 2], ['id >=' => 3])], [1, 3]];
		yield 'more operators' => [[Criteria::anyOf(['id <>' => 1, 'id <=' => 2], ['id >' => 2])], [2, 3]];
		yield 'like' => [[Criteria::anyOf(['title LIKE' => 'Sample%'], ['content LIKE' => '%third%'])], [1, 3]];
		yield 'not like' => [[Criteria::anyOf(['title NOT LIKE' => '%Article'], ['title NOT LIKE' => 'S%', 'id <' => 3])], [2]];

		yield 'criteria before the group' => [['symbol' => null, Criteria::anyOf(['id' => 1], ['id' => 2])], [2]];
		yield 'criteria after the group' => [[Criteria::anyOf(['id' => 1], ['id' => 2]), 'symbol' => null], [2]];
		yield 'criteria around the group' => [['id >' => 1, Criteria::anyOf(['id' => 1], ['id' => 2], ['id' => 3]), 'title LIKE' => 'Third%'], [3]];
		// Without the parentheses around the group, "id = 1 AND title = Sample OR title = Third" would match the third article too.
		yield 'group is not split by the criteria before it' => [['id' => 1, Criteria::anyOf(['title' => 'Sample Article'], ['title' => 'Third Article'])], [1]];
		yield 'group is not split by the criteria after it' => [[Criteria::anyOf(['title' => 'Third Article'], ['title' => 'Sample Article']), 'id' => 1], [1]];
		// Without the parentheses around an alternative, "symbol IS NULL AND id = 2 OR id = 3" would match the same, so the alternatives are swapped too.
		yield 'alternative is not split by the other ones' => [['symbol !=' => null, Criteria::anyOf(['id' => 3], ['id' => 1, 'title' => 'Sample Article'])], [1]];
		yield 'two groups' => [[Criteria::anyOf(['id' => 1], ['id' => 2]), Criteria::anyOf(['id' => 2], ['id' => 3])], [2]];
		yield 'two groups and criteria' => [[Criteria::anyOf(['id' => 1], ['id' => 2]), 'symbol' => null, Criteria::anyOf(['id' => 2], ['id' => 3])], [2]];
		yield 'nested group' => [
			[Criteria::anyOf(['id' => 1], ['symbol' => null, Criteria::anyOf(['title LIKE' => 'Another%'], ['title LIKE' => 'Missing%'])])],
			[1, 2],
		];
		yield 'group nested twice' => [
			[Criteria::anyOf(['id' => 3], [Criteria::anyOf(['id' => 1, Criteria::anyOf(['title' => 'Missing Article'], ['symbol' => 'sym'])], ['id' => 20])])],
			[1, 3],
		];
		yield 'nested group that does not match' => [
			[Criteria::anyOf(['id' => 3], ['id' => 1, Criteria::anyOf(['title' => 'Missing Article'], ['symbol' => null])])],
			[3],
		];
		// Every value is bound to a parameter of its own name.
		yield 'same field and operator in the criteria and in every alternative' => [
			['id >=' => 1, Criteria::anyOf(['id >=' => 3], ['id >=' => 2, 'title' => 'Another Article']), 'id <=' => 3],
			[2, 3],
		];

		yield 'is null' => [[Criteria::anyOf(['symbol' => null], ['id' => 1])], [1, 2, 3]];
		yield 'is not null' => [[Criteria::anyOf(['symbol !=' => null], ['id' => 3])], [1, 3]];
		yield 'in lists' => [[Criteria::anyOf(['id' => [1, 2]], ['title' => ['Third Article']])], [1, 2, 3]];
		yield 'not in list' => [[Criteria::anyOf(['id !=' => [1, 2]], ['id' => 1])], [1, 3]];
		yield 'list with a null item' => [[Criteria::anyOf(['symbol' => ['other', null]], ['id' => 10])], [2, 3]];
		yield 'list with a null item beside another criteria' => [[Criteria::anyOf(['symbol' => ['sym', null], 'id >' => 2], ['id' => 1])], [1, 3]];
		yield 'list with only a null item' => [[Criteria::anyOf(['symbol' => [null], 'id' => 2], ['symbol !=' => [null]])], [1, 2]];
		yield 'empty list matches nothing' => [[Criteria::anyOf(['id' => []], ['id' => 2])], [2]];
		yield 'only empty lists match nothing' => [[Criteria::anyOf(['id' => []], ['title' => []])], []];
		// "NOT IN ()" matches every row, so does the whole group.
		yield 'empty excluded list matches everything' => [[Criteria::anyOf(['id !=' => []], ['id' => 2])], [1, 2, 3]];
		yield 'empty excluded list after an alternative with a value' => [[Criteria::anyOf(['id' => 2], ['id !=' => []])], [1, 2, 3]];
		yield 'empty excluded list beside another criteria' => [[Criteria::anyOf(['id !=' => [], 'title' => 'Sample Article'], ['id' => 2])], [1, 2]];
		yield 'only an empty excluded list' => [['id <' => 3, Criteria::anyOf(['id !=' => []])], [1, 2]];
		yield 'empty excluded list in a nested group' => [[Criteria::anyOf(['id' => 1], ['symbol' => null, Criteria::anyOf(['id !=' => []], ['id' => 1])])], [1, 2, 3]];

		yield 'related field' => [[Criteria::anyOf(['author.name' => 'Jane Smith'], ['id' => 1])], [1, 2]];
		yield 'related field in every alternative' => [[Criteria::anyOf(['author.name' => 'Jane Smith'], ['author.id' => 1, 'symbol' => null])], [2, 3]];
		yield 'related field in the criteria and in an alternative' => [['author.name LIKE' => 'John%', Criteria::anyOf(['author.id' => 2], ['symbol' => null])], [3]];
		// The relations are left joined, an article whose author has no role can match by another alternative.
		yield 'nested related field' => [[Criteria::anyOf(['author.role.name' => 'Administrator'], ['id' => 1])], [1, 2]];
		yield 'nested relation is null' => [[Criteria::anyOf(['author.role' => null, 'id' => 3], ['author.role.id !=' => null])], [2, 3]];
		yield 'association by identifier' => [[Criteria::anyOf(['author' => 2], ['id' => 1])], [1, 2]];
		yield 'association in list' => [[Criteria::anyOf(['author' => [2]], ['author !=' => [1, 2]])], [2]];

		yield 'backed enum and datetime' => [
			[Criteria::anyOf(['type' => ArticleType::News, 'id' => 1], ['createdAt >' => new DateTimeImmutable('2022-01-01')])],
			[1],
		];
		yield 'backed enums in a list' => [[Criteria::anyOf(['type' => [ArticleType::Blog]], ['type' => [ArticleType::News, ArticleType::Blog], 'id' => 3])], [3]];
		yield 'lists of datetimes' => [
			[Criteria::anyOf(['createdAt' => [new DateTimeImmutable(self::FixtureCreatedAt)], 'id' => 2], ['createdAt' => [new DateTime('2022-01-01')]])],
			[2],
		];
		yield 'custom type objects' => [[Criteria::anyOf(['symbol' => new Symbol('sym')], ['symbol' => [new Symbol('other')]])], [1]];
		yield 'traversable values' => [[Criteria::anyOf(['id' => new ArrayIterator([1])], ['id' => new ArrayIterator(['key' => 3])])], [1, 3]];
	}

	/**
	 * @return iterable<string, array{string, array<array-key, mixed>, list<int>}>
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
	 * @param array<array-key, mixed> $criteria
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('provideCriteriaForEachQueryType')]
	public function testFindBy(string $queryType, array $criteria, array $expectedIds): void
	{
		$this->persistFixtures();

		$this->assertSame($expectedIds, $this->assertSingleQuery(fn (): array => $this->findIds($queryType, $criteria)));
	}

	/**
	 * @param array<array-key, mixed> $criteria
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

	/**
	 * Deleting is checked against the rows that are left, DQL DELETE takes the criteria without the joins of related fields.
	 *
	 * @param array<array-key, mixed> $criteria
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('provideCriteria')]
	public function testDeleteByAgreesWithFindBy(array $criteria, array $expectedIds): void
	{
		if (self::hasRelatedCriteria($criteria)) {
			$this->expectException(LogicException::class);
			$this->expectExceptionMessage('DQL DELETE does not support joins');
		}

		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(count($expectedIds), $queries->deleteBy(Article::class, $criteria));
		$this->assertSame(
			array_values(array_diff([1, 2, 3], $expectedIds)),
			$queries->scalars->findColumnValuesBy(Article::class, 'id', orderBy: self::OrderById)->asArray(),
		);
	}

	public function testGeneratedDql(): void
	{
		$queryBuilder = $this->createQueryBuilderFactory()->create(Article::class, ['id'], [
			'id >=' => 1,
			Criteria::anyOf(
				['title' => 'Sample Article'],
				['id' => [1, 2], 'symbol' => ['sym', null]],
				['id !=' => []],
				['author.name' => 'Jane Smith', Criteria::anyOf(['id' => 2], ['id' => 3])],
			),
			'content LIKE' => '%article%',
		], queryType: QueryType::Scalar);

		$this->assertSame(
			'SELECT e0.id AS id FROM Tests\Entity\Article e0 LEFT JOIN e0.author e1'
			. ' WHERE e0.id >= :param_e0_1 AND e0.content LIKE :param_e0_2 AND ('
			. '(e0.title = :param_e0_3'
			. ' OR (e0.id IN (:param_e0_4) AND (e0.symbol IN (:param_e0_5) OR e0.symbol IS NULL))'
			. ' OR 1 = 1'
			. ' OR (e1.name = :param_e0_6 AND (e0.id = :param_e0_7 OR e0.id = :param_e0_8)))'
			. ')',
			$queryBuilder->getDQL(),
		);
		$this->assertSame(
			[
				'param_e0_1' => 1,
				'param_e0_2' => '%article%',
				'param_e0_3' => 'Sample Article',
				'param_e0_4' => [1, 2],
				'param_e0_5' => ['sym'],
				'param_e0_6' => 'Jane Smith',
				'param_e0_7' => 2,
				'param_e0_8' => 3,
			],
			self::getParameterValues($queryBuilder),
		);
	}

	public function testGeneratedDqlOfSingleAlternativeHasNoParentheses(): void
	{
		$factory = $this->createQueryBuilderFactory();

		$this->assertSame(
			'SELECT e0.id AS id FROM Tests\Entity\Article e0 WHERE e0.id = :param_e0_1',
			$factory->create(Article::class, ['id'], [Criteria::anyOf(['id' => 1])], queryType: QueryType::Scalar)->getDQL(),
		);
		$this->assertSame(
			'SELECT e0.id AS id FROM Tests\Entity\Article e0 WHERE (e0.id = :param_e0_1 AND e0.symbol IS NULL)',
			$factory->create(Article::class, ['id'], [Criteria::anyOf(['id' => 1, 'symbol' => null])], queryType: QueryType::Scalar)->getDQL(),
		);
		$this->assertSame(
			'SELECT e0.id AS id FROM Tests\Entity\Article e0 WHERE (e0.id = :param_e0_1 OR e0.symbol IS NULL)',
			$factory->create(Article::class, ['id'], [Criteria::anyOf(['id' => 1], ['symbol' => null])], queryType: QueryType::Scalar)->getDQL(),
		);
	}

	public function testGeneratedDqlOfToManyAssociation(): void
	{
		$queryBuilder = $this->createQueryBuilderFactory()->create(Article::class, ['id'], [
			'hashTags.hashtag' => 'php',
			Criteria::anyOf(
				['hashTags.hashtag' => 'doctrine', 'hashTags.hashtag LIKE' => 'd%'],
				['hashTags.hashtag' => null],
			),
		], queryType: QueryType::Scalar);

		$this->assertSame(
			'SELECT e0.id AS id FROM Tests\Entity\Article e0'
			. ' WHERE (EXISTS (SELECT 1 FROM Tests\Entity\ArticleHashtag e1 WHERE e1.article = e0 AND e1.hashtag = :param_e1_1))'
			. ' AND ('
			. '(EXISTS (SELECT 1 FROM Tests\Entity\ArticleHashtag e2 WHERE e2.article = e0 AND e2.hashtag = :param_e2_1 AND e2.hashtag LIKE :param_e2_2)'
			. ' OR (EXISTS (SELECT 1 FROM Tests\Entity\ArticleHashtag e3 WHERE e3.article = e0 AND e3.hashtag IS NULL)'
			. ' OR NOT EXISTS (SELECT 1 FROM Tests\Entity\ArticleHashtag e4 WHERE e4.article = e0)))'
			. ')',
			$queryBuilder->getDQL(),
		);
		$this->assertSame(
			['param_e1_1' => 'php', 'param_e2_1' => 'doctrine', 'param_e2_2' => 'd%'],
			self::getParameterValues($queryBuilder),
		);
	}

	public function testEveryFindMethod(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$criteria = [Criteria::anyOf(['id' => 1], ['title' => 'Third Article'])];

		$this->assertSame(
			['id' => 3, 'title' => 'Third Article'],
			$queries->arrays->findOneBy(Article::class, $criteria, ['id' => SortDirection::Descending], select: ['id', 'title']),
		);
		$this->assertSame(3, $queries->objects->findOneBy(Article::class, $criteria, ['id' => SortDirection::Descending])?->getId());
		$this->assertNull($queries->scalars->findOneBy(Article::class, [Criteria::anyOf(['id' => 10], ['id' => 20])]));
		$this->assertSame(
			[1 => ['title' => 'Sample Article'], 3 => ['title' => 'Third Article']],
			$queries->scalars->findIndexedBy(Article::class, 'id', $criteria, self::OrderById, select: ['title'])->asArray(),
		);
		$this->assertSame(
			[1 => 'Sample Article', 3 => 'Third Article'],
			$queries->scalars->findPairsBy(Article::class, 'id', 'title', $criteria, self::OrderById)->asArray(),
		);
		$this->assertSame(
			['Sample Article', 'Third Article'],
			$queries->scalars->findColumnValuesBy(Article::class, 'title', $criteria, self::OrderById)->asArray(),
		);
		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(Article::class, 'author.id', $criteria, distinct: true)->asArray(),
			'Both articles are written by John Doe.',
		);
		$this->assertSame('Third Article', $queries->scalars->findSingleColumnValueBy(Article::class, 'title', $criteria, orderBy: ['id' => SortDirection::Descending]));
		$this->assertSame(
			[3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', $criteria, self::OrderById, pagination: new Pagination(1, 1))->asArray(),
		);
		$this->assertSame(
			[1, 3],
			array_map(
				static fn (Article $article): int => $article->getId(),
				iterator_to_array($queries->objects->findBy(Article::class, $criteria, self::OrderById)->yield(), false),
			),
		);
		$this->assertSame([1, 3], self::column($queries->arrays->findBy(Article::class, $criteria, self::OrderById)->yield(), 'id'));
		$this->assertSame(2, $queries->countBy(Article::class, $criteria));
		$this->assertSame(1, $queries->countBy(Article::class, $criteria, 'author.id', distinct: true));
		$this->assertTrue($queries->existsBy(Article::class, $criteria));
		$this->assertFalse($queries->existsBy(Article::class, [Criteria::anyOf(['id' => 10], ['id' => 20])]));
	}

	public function testGroupCanBeUsedAgain(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$firstOrThird = Criteria::anyOf(['id' => 1], ['id' => 3]);

		$this->assertSame([1, 3], $queries->scalars->findColumnValuesBy(Article::class, 'id', [$firstOrThird], self::OrderById)->asArray());
		$this->assertSame([3], $queries->scalars->findColumnValuesBy(Article::class, 'id', [$firstOrThird, 'symbol' => null])->asArray());
		$this->assertSame(
			[1, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [$firstOrThird, $firstOrThird], self::OrderById)->asArray(),
			'The same group twice in one query binds its values twice.',
		);
		$this->assertSame(
			[1, 2, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [Criteria::anyOf([$firstOrThird], ['id' => 2])], self::OrderById)->asArray(),
		);
	}

	public function testNamedAndUnpackedAlternatives(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			[1, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [Criteria::anyOf(first: ['id' => 1], third: ['id' => 3])], self::OrderById)->asArray(),
		);
		$this->assertSame(
			[2, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [Criteria::anyOf(...[5 => ['id' => 2], 9 => ['id' => 3]])], self::OrderById)->asArray(),
		);
	}

	/**
	 * An inner join requires the relation of every row, also of the rows that match by another alternative.
	 */
	public function testInnerJoinFiltersTheRowsOfEveryAlternative(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$criteria = [Criteria::anyOf(['author.role.name' => 'Administrator'], ['id' => 1])];

		$this->assertSame([1, 2], $queries->scalars->findColumnValuesBy(Article::class, 'id', $criteria, self::OrderById)->asArray());
		$this->assertSame([2], $queries->scalars->findColumnValuesBy(Article::class, 'id', $criteria, joinConfig: 'inner')->asArray());
		$this->assertSame([2], $queries->scalars->findColumnValuesBy(Article::class, 'id', $criteria, joinConfig: ['author.role' => 'inner'])->asArray());
		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', $criteria, self::OrderById, joinConfig: ['author' => 'inner'])->asArray(),
			'Every article has an author.',
		);
		$this->assertSame(1, $queries->countBy(Article::class, $criteria, joinConfig: 'inner'));
	}

	public function testSubQueryInAlternative(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$authorsNamed = static fn (string $name): object => $queries->subQuery(Author::class, ['name' => $name], select: ['id']);

		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [Criteria::anyOf(['author' => $authorsNamed('Jane Smith')], ['id' => 1])], self::OrderById)->asArray(),
		);
		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				Criteria::anyOf(['author' => $authorsNamed('Jane Smith')], ['author' => $authorsNamed('John Doe'), 'symbol !=' => null]),
			], self::OrderById)->asArray(),
			'Each sub-query binds its own parameter.',
		);
		$this->assertSame(
			[2, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				Criteria::anyOf(['author !=' => $authorsNamed('John Doe')], ['id' => 3]),
			], self::OrderById)->asArray(),
		);
		$this->assertSame(3, $queries->countBy(Article::class, [Criteria::anyOf(['author' => $authorsNamed('Jane Smith')], ['author' => $authorsNamed('John Doe')])]));
	}

	public function testGroupInSubQuery(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			[2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				'author' => $queries->subQuery(Author::class, [Criteria::anyOf(['name' => 'Jane Smith'], ['name' => 'Nobody'])], select: ['id']),
			])->asArray(),
		);
		$this->assertSame(
			[2, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				'id >' => 0,
				Criteria::anyOf(
					['author' => $queries->subQuery(Author::class, ['id >' => 0, Criteria::anyOf(['name' => 'Jane Smith'], ['id' => 10])], select: ['id'])],
					['id' => 3],
				),
			], self::OrderById)->asArray(),
			'The groups of the query and of its sub-query bind the values of the same fields.',
		);
		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(Author::class, 'id', [
				'id' => $queries->subQuery(Article::class, [Criteria::anyOf(['author.role.name' => 'Nobody'], ['symbol' => 'sym'])], select: ['author']),
			])->asArray(),
			'A related field of the sub-query is joined in the sub-query.',
		);
	}

	/**
	 * A to-many association in an alternative is checked by a sub-query of that alternative only. A sub-query
	 * on the related entity checks all its criteria and groups against one related entity.
	 */
	public function testGroupInSubQueryOnRelatedEntityMatchesOneRelatedEntity(): void
	{
		$queries = $this->createQueriesWithHashtags();
		$articlesWithHashtag = static fn (string $pattern): object => $queries->subQuery(
			ArticleHashtag::class,
			['hashtag LIKE' => $pattern, Criteria::anyOf(['hashtag' => 'php'], ['hashtag' => 'missing'])],
			select: ['article'],
		);

		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', ['id' => $articlesWithHashtag('p%')], self::OrderById)->asArray(),
		);
		$this->assertSame(
			[],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', ['id' => $articlesWithHashtag('d%')])->asArray(),
			'The first article has "doctrine" and "php", but no hashtag of it starts with "d" and is "php".',
		);
		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				'hashTags.hashtag LIKE' => 'd%',
				Criteria::anyOf(['hashTags.hashtag' => 'php'], ['hashTags.hashtag' => 'missing']),
			])->asArray(),
			'The criteria of the association around the group can match another hashtag.',
		);
	}

	public function testQueryBuilderInAlternative(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$janeSmith = $this->getEntityManager()->createQueryBuilder()
			->select('author.id')
			->from(Author::class, 'author')
			->where('author.name = :authorName')
			->setParameter('authorName', 'Jane Smith');

		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [Criteria::anyOf(['author' => $janeSmith], ['id' => 1])], self::OrderById)->asArray(),
		);
		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [Criteria::anyOf(['id' => 1], ['author' => $janeSmith->getQuery()])], self::OrderById)->asArray(),
		);
	}

	public function testToManyAssociationReturnsEachRootEntityOnce(): void
	{
		$queries = $this->createQueriesWithHashtags();
		$phpOrDoctrine = [Criteria::anyOf(['hashTags.hashtag' => 'php'], ['hashTags.hashtag' => 'doctrine'])];

		$this->assertSame(
			[1, 2],
			array_map(
				static fn (Article $article): int => $article->getId(),
				iterator_to_array($queries->objects->findBy(Article::class, $phpOrDoctrine, self::OrderById)->yield(), false),
			),
			'The first article has both hashtags, the query is not joined so it can be iterated.',
		);
		$this->assertSame([1, 2], self::column($queries->arrays->findBy(Article::class, $phpOrDoctrine, self::OrderById)->asArray(), 'id'));
		$this->assertSame([1, 2], $queries->scalars->findColumnValuesBy(Article::class, 'id', $phpOrDoctrine, self::OrderById)->asArray());
		$this->assertSame(
			[2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', $phpOrDoctrine, self::OrderById, pagination: new Pagination(1, 1))->asArray(),
			'The pagination counts the articles.',
		);
		$this->assertSame(2, $queries->countBy(Article::class, $phpOrDoctrine));
		$this->assertTrue($queries->existsBy(Article::class, $phpOrDoctrine));
	}

	public function testToManyAssociationBesideAnotherAlternative(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [Criteria::anyOf(['hashTags.hashtag' => 'doctrine'], ['id' => 3])], self::OrderById)->asArray(),
		);
		$this->assertSame(
			[2, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				Criteria::anyOf(['hashTags.hashtag' => 'php', 'author.name' => 'Jane Smith'], ['title LIKE' => 'Third%']),
			], self::OrderById)->asArray(),
		);
		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				'symbol !=' => null,
				Criteria::anyOf(['hashTags.hashtag' => 'php'], ['id' => 3]),
			])->asArray(),
		);
		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				Criteria::anyOf(['id' => 2], ['id' => 1, Criteria::anyOf(['hashTags.hashtag' => 'doctrine'], ['hashTags.hashtag' => 'missing'])]),
			], self::OrderById)->asArray(),
		);
	}

	public function testCriteriaOfOneAlternativeMatchTheSameRelatedEntity(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				Criteria::anyOf(['hashTags.hashtag' => 'php', 'hashTags.hashtag LIKE' => 'd%'], ['id' => 3]),
			])->asArray(),
			'The first article has "php" and "doctrine", but no hashtag is both.',
		);
		$this->assertSame(
			[1, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				Criteria::anyOf(['hashTags.hashtag LIKE' => '%o%', 'hashTags.hashtag !=' => 'php'], ['id' => 3]),
			], self::OrderById)->asArray(),
		);
	}

	public function testRelatedEntityIsNotSharedWithTheCriteriaAroundTheGroup(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				'hashTags.hashtag' => 'php',
				Criteria::anyOf(['hashTags.hashtag' => 'doctrine'], ['id' => 10]),
			])->asArray(),
			'One hashtag of the first article is "php" and another one is "doctrine".',
		);
		$this->assertSame(
			[],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', ['hashTags.hashtag' => 'php', 'hashTags.hashtag LIKE' => 'd%'])->asArray(),
			'Without the group, both criteria have to match the same hashtag.',
		);
	}

	public function testNullCriteriaOfToManyAssociationMatchRootEntitiesWithoutRelatedEntity(): void
	{
		$queries = $this->createQueriesWithHashtags();
		$withoutHashtagOrFirst = [Criteria::anyOf(['hashTags.hashtag' => null], ['id' => 1])];

		$this->assertSame([1, 3], $queries->scalars->findColumnValuesBy(Article::class, 'id', $withoutHashtagOrFirst, self::OrderById)->asArray());
		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', $withoutHashtagOrFirst, joinConfig: ['hashTags' => 'inner'])->asArray(),
			'An inner join of the hashtags requires one.',
		);
		$this->assertSame(
			[2, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				Criteria::anyOf(['hashTags.hashtag' => ['missing', null]], ['hashTags.hashtag !=' => null, 'symbol' => null]),
			], self::OrderById)->asArray(),
		);
		$this->assertSame(2, $queries->countBy(Article::class, $withoutHashtagOrFirst));
	}

	public function testCriteriaFilterTheRowsOfASelectedAssociation(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[['id' => 1, 'hashtag' => 'doctrine'], ['id' => 2, 'hashtag' => 'php']],
			$queries->scalars->findBy(
				Article::class,
				[Criteria::anyOf(['hashTags.hashtag' => 'doctrine'], ['id' => 2])],
				self::OrderById,
				select: ['id', 'hashTags.hashtag'],
			)->asArray(),
			'The selected association is joined, the criteria filter its rows: "php" of the first article does not match.',
		);
		$this->assertSame(
			[['id' => 1, 'hashtag' => 'doctrine'], ['id' => 3, 'hashtag' => null]],
			iterator_to_array($queries->scalars->findBy(
				Article::class,
				[Criteria::anyOf(['hashTags.hashtag' => 'doctrine'], ['id' => 3])],
				self::OrderById,
				select: ['id', 'hashTags.hashtag'],
			)->yield(), false),
			'The third article has no hashtag, it matches by the other alternative.',
		);
		$this->assertSame(
			['php', 'php'],
			$queries->scalars->findColumnValuesBy(
				Article::class,
				'hashTags.hashtag',
				[Criteria::anyOf(['hashTags.hashtag LIKE' => 'p%', 'id' => 1], ['hashTags.hashtag' => 'php'])],
				self::OrderById,
			)->asArray(),
		);
		$this->assertSame(2, $queries->countBy(Article::class, [Criteria::anyOf(['hashTags.hashtag' => 'doctrine'], ['id' => 2])], 'hashTags.hashtag'));
		$this->assertSame(1, $queries->countBy(Article::class, [Criteria::anyOf(['hashTags.hashtag' => 'doctrine'], ['id' => 3])], 'hashTags.hashtag'));
	}

	public function testCriteriaFilterTheRowsOfAnOrderedAssociation(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1, 1, 2],
			$queries->scalars->findColumnValuesBy(
				Article::class,
				'id',
				[Criteria::anyOf(['hashTags.hashtag LIKE' => '%p%'], ['hashTags.hashtag LIKE' => 'd%'])],
				['id' => SortDirection::Ascending, 'hashTags.hashtag' => SortDirection::Ascending],
			)->asArray(),
			'Ordering by a field of the hashtags joins them, a row per matching hashtag.',
		);
	}

	public function testManyToManyAssociation(): void
	{
		$this->persistFixtures(static function (): array {
			$php = new NoteTag(1, 'php');
			$doctrine = new NoteTag(2, 'doctrine');

			return [
				$php,
				$doctrine,
				new Note(1, 'First', [$php, $doctrine]),
				new Note(2, 'Second', [$php]),
				new Note(3, 'Third'),
			];
		});
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			[1, 3],
			$queries->scalars->findColumnValuesBy(Note::class, 'id', [Criteria::anyOf(['tags.name' => 'doctrine'], ['title' => 'Third'])], self::OrderById)->asArray(),
		);
		$this->assertSame(
			[2, 3],
			$queries->scalars->findColumnValuesBy(Note::class, 'id', [Criteria::anyOf(['tags.name' => null], ['title' => 'Second'])], self::OrderById)->asArray(),
		);
		$this->assertSame(
			['php', 'doctrine'],
			$queries->scalars->findColumnValuesBy(NoteTag::class, 'name', [Criteria::anyOf(['notes.title' => 'Second'], ['name' => 'doctrine'])], self::OrderById)->asArray(),
			'The inverse side of the association.',
		);
		$this->assertSame(3, $queries->countBy(Note::class, [Criteria::anyOf(['tags.name' => ['php', 'doctrine']], ['id' => 3])]));
	}

	/**
	 * | ProjectRole | label       | member     | author role   |
	 * |-------------|-------------|------------|---------------|
	 * | 1           | Maintainer  | John Doe   | -             |
	 * | 2           | Contributor | Jane Smith | Administrator |
	 * | 3           | Observer    | -          | -             |
	 */
	public function testRelationsBelowToManyAssociation(): void
	{
		$this->persistFixtures(static function (Author $johnDoe, Role $administrator, Author $janeSmith): array {
			$maintainer = new ProjectRole(1, 'Maintainer');
			$contributor = new ProjectRole(2, 'Contributor');

			return [
				$maintainer,
				$contributor,
				new ProjectRole(3, 'Observer'),
				new ProjectMember(1, $johnDoe, $maintainer),
				new ProjectMember(2, $janeSmith, $contributor),
			];
		});
		$queries = $this->createDoctrineQueries();
		$administratorOrObserver = [Criteria::anyOf(['members.author.role.name' => 'Administrator'], ['label' => 'Observer'])];
		$withoutRoleOrContributor = [Criteria::anyOf(['members.author.role.name' => null], ['label' => 'Contributor'])];

		$this->assertSame([2, 3], $queries->scalars->findColumnValuesBy(ProjectRole::class, 'id', $administratorOrObserver, self::OrderById)->asArray());
		$this->assertSame(
			[1, 2, 3],
			$queries->scalars->findColumnValuesBy(ProjectRole::class, 'id', $withoutRoleOrContributor, self::OrderById)->asArray(),
			'The author of the first member has no role, the third role has no member.',
		);
		$this->assertSame(
			[2],
			$queries->scalars->findColumnValuesBy(ProjectRole::class, 'id', $withoutRoleOrContributor, self::OrderById, joinConfig: ['members.author.role' => 'inner'])->asArray(),
			'An inner join of the role of the author requires one, and with it a member: only the other alternative matches.',
		);
		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(ProjectMember::class, 'id', [
				Criteria::anyOf(['role.members.author.name' => 'Jane Smith'], ['author.name' => 'John Doe']),
			], self::OrderById)->asArray(),
			'A to-many association of a related entity.',
		);
	}

	public function testNestedToManyAssociations(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1, 3],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', [
				Criteria::anyOf(['hashTags.article.hashTags.hashtag' => 'doctrine'], ['id' => 3]),
			], self::OrderById)->asArray(),
		);
	}

	public function testToManyAssociationInGroupOfSubQuery(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(Author::class, 'id', [
				'id' => $queries->subQuery(
					Article::class,
					[Criteria::anyOf(['hashTags.hashtag' => 'doctrine'], ['title' => 'Another Article'])],
					select: ['author'],
				),
			], self::OrderById)->asArray(),
		);
	}

	public function testDeleteBy(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(1, $queries->deleteBy(Article::class, ['symbol' => null, Criteria::anyOf(['id' => 1], ['title' => 'Third Article'])]));
		$this->assertSame([1, 2], $queries->scalars->findColumnValuesBy(Article::class, 'id', orderBy: self::OrderById)->asArray());
		$this->assertSame(0, $queries->deleteBy(Article::class, [Criteria::anyOf(['id' => 10], ['id' => 20])]));
		$this->assertSame(
			2,
			$queries->deleteBy(Article::class, [
				Criteria::anyOf(['author' => $queries->subQuery(Author::class, ['name' => 'Jane Smith'], select: ['id'])], ['id' => 1]),
			]),
		);
		$this->assertSame(0, $queries->countBy(Article::class));
	}

	public function testDeleteByRejectsRelatedCriteriaOfAlternative(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$message = 'Cannot delete Tests\Entity\Article by criteria "%s", DQL DELETE does not support joins.'
			. ' Filter by the association with a sub-query instead, e.g. [\'%s\' => $queries->subQuery(...)].';

		$this->assertThrows(
			LogicException::class,
			sprintf($message, 'author.name', 'author'),
			static fn () => $queries->deleteBy(Article::class, [Criteria::anyOf(['id' => 1], ['author.name' => 'John Doe'])]),
		);
		$this->assertThrows(
			LogicException::class,
			sprintf($message, 'hashTags.hashtag LIKE', 'hashTags'),
			static fn () => $queries->deleteBy(Article::class, [
				'id' => 1,
				Criteria::anyOf(['id' => 2], ['id' => 3, Criteria::anyOf(['title' => 'Third Article'], ['hashTags.hashtag LIKE' => 'p%'])]),
			]),
		);
		$this->assertThrows(
			LogicException::class,
			sprintf($message, 'author.name', 'author'),
			static fn () => $queries->deleteBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2]), 'author.name' => 'John Doe']),
			'Criteria after a group are checked too.',
		);
		$this->assertThrows(
			LogicException::class,
			sprintf($message, 'author.role.name', 'author'),
			static fn () => $queries->deleteBy(Article::class, [Criteria::anyOf(['id' => 1]), Criteria::anyOf(['id' => 2], ['author.role.name' => 'Administrator'])]),
			'Every group is checked.',
		);
		$this->assertSame(3, $queries->countBy(Article::class), 'Nothing is deleted.');
	}

	public function testItemWithoutFieldIsRejected(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$message = 'Criteria item %d has no field name, only Criteria::anyOf() can be passed without one, %s given.';

		$calls = [
			'objects' => static fn () => $queries->objects->findBy(Article::class, ['title']),
			'arrays' => static fn () => $queries->arrays->findOneBy(Article::class, ['title']),
			'scalars' => static fn () => $queries->scalars->findColumnValuesBy(Article::class, 'id', ['title']),
			'count' => static fn () => $queries->countBy(Article::class, ['title']),
			'exists' => static fn () => $queries->existsBy(Article::class, ['title']),
			'delete' => static fn () => $queries->deleteBy(Article::class, ['title']),
			'sub-query' => static fn () => $queries->existsBy(Article::class, ['author' => $queries->subQuery(Author::class, ['name'], select: ['id'])]),
		];

		foreach ($calls as $name => $call) {
			$this->assertThrows(InvalidArgumentException::class, sprintf($message, 0, 'string'), $call, $name);
		}

		$this->assertThrows(
			InvalidArgumentException::class,
			sprintf($message, 1, 'array'),
			static fn () => $queries->existsBy(Article::class, ['id' => 1, Criteria::anyOf(['id' => 2]), ['id' => 3]]),
		);
		$this->assertThrows(
			InvalidArgumentException::class,
			sprintf($message, 5, 'null'),
			static fn () => $queries->existsBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2, 5 => null])]),
			'An item of an alternative.',
		);
		$this->assertThrows(
			InvalidArgumentException::class,
			sprintf($message, 7, 'int'),
			static fn () => $queries->deleteBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2, 7 => 3])]),
		);
		$this->assertThrows(
			InvalidArgumentException::class,
			sprintf($message, 12, 'string'),
			static fn () => $queries->existsBy(Article::class, ['12' => 'PHP turns a numeric key into an integer']),
		);
		$this->assertSame(3, $queries->countBy(Article::class), 'Nothing is deleted.');
	}

	public function testGroupComparedWithFieldIsRejected(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$message = 'Criteria::anyOf() cannot be compared with "%s", pass it among the criteria without a key.';
		$group = Criteria::anyOf(['id' => 1], ['id' => 2]);

		$this->assertThrows(InvalidArgumentException::class, sprintf($message, 'title'), static fn () => $queries->existsBy(Article::class, ['title' => $group]));
		$this->assertThrows(InvalidArgumentException::class, sprintf($message, 'id !='), static fn () => $queries->countBy(Article::class, ['id !=' => $group]));
		$this->assertThrows(
			InvalidArgumentException::class,
			sprintf($message, 'hashTags.hashtag'),
			static fn () => $queries->existsBy(Article::class, ['hashTags.hashtag' => $group]),
		);
		$this->assertThrows(
			InvalidArgumentException::class,
			sprintf($message, 'author.name'),
			static fn () => $queries->existsBy(Article::class, [Criteria::anyOf(['id' => 1], ['author.name' => $group])]),
		);
		$this->assertThrows(InvalidArgumentException::class, sprintf($message, 'title'), static fn () => $queries->deleteBy(Article::class, ['title' => $group]));
		$this->assertSame(3, $queries->countBy(Article::class), 'Nothing is deleted.');
	}

	public function testAlternativesAreValidatedAsCriteria(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertThrows(
			FieldNotExistsException::class,
			'Field "unknown" does not exist in entity "Tests\Entity\Article".',
			static fn () => $queries->existsBy(Article::class, [Criteria::anyOf(['id' => 1], ['unknown' => 1])]),
		);
		$this->assertThrows(
			FieldNotExistsException::class,
			'Field "unknown" does not exist in entity "Tests\Entity\Article".',
			static fn () => $queries->deleteBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2, Criteria::anyOf(['unknown' => 1])])]),
		);
		$this->assertThrows(
			InvalidAssociationPathException::class,
			null,
			static fn () => $queries->existsBy(Article::class, [Criteria::anyOf(['title.length >' => 1], ['id' => 1])]),
		);
		$this->assertThrows(
			ToManyAssociationException::class,
			'Field "hashTags" of entity "Tests\Entity\Article" is a to-many association, use a field of the related entity instead, e.g. "hashTags.<field>".',
			static fn () => $queries->existsBy(Article::class, [Criteria::anyOf(['id' => 1], ['hashTags' => 1])]),
		);
		$this->assertThrows(
			InvalidArgumentException::class,
			'Unsupported operator "BETWEEN" in criteria "id BETWEEN". Supported operators are: =, !=, <>, <, <=, >, >=, LIKE, NOT LIKE.',
			static fn () => $queries->existsBy(Article::class, [Criteria::anyOf(['id' => 1], ['id BETWEEN' => 1])]),
		);
		$this->assertThrows(
			InvalidArgumentException::class,
			'Only = and != operators are allowed with NULL value',
			static fn () => $queries->existsBy(Article::class, [Criteria::anyOf(['id' => 1], ['symbol >' => null])]),
		);
		$this->assertThrows(
			InvalidArgumentException::class,
			'Field cannot be empty',
			static fn () => $queries->existsBy(Article::class, [Criteria::anyOf(['id' => 1], ['' => 1])]),
		);
		$this->assertSame(3, $queries->countBy(Article::class), 'Nothing is deleted.');
	}

	/**
	 * @param class-string<Throwable> $expectedException
	 * @param callable(): mixed $call
	 */
	private function assertThrows(string $expectedException, ?string $expectedMessage, callable $call, string $description = ''): void
	{
		try {
			$call();
		} catch (Throwable $exception) {
			$this->assertSame($expectedException, $exception::class, $description);
			if ($expectedMessage !== null) {
				$this->assertSame($expectedMessage, $exception->getMessage(), $description);
			}

			return;
		}

		$this->fail(sprintf('Expected %s. %s', $expectedException, $description));
	}

	/**
	 * @param array<array-key, mixed> $criteria
	 */
	private static function hasRelatedCriteria(array $criteria): bool
	{
		foreach ($criteria as $criteriaKey => $value) {
			if (is_string($criteriaKey)) {
				if (str_contains(explode(' ', $criteriaKey)[0], '.')) {
					return true;
				}

				continue;
			}

			self::assertInstanceOf(AnyOf::class, $value);
			foreach ($value->alternatives as $alternative) {
				if (self::hasRelatedCriteria($alternative)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getParameterValues(QueryBuilder $queryBuilder): array
	{
		$values = [];
		foreach ($queryBuilder->getParameters() as $parameter) {
			self::assertInstanceOf(Parameter::class, $parameter);
			self::assertArrayNotHasKey($parameter->getName(), $values, 'Every parameter has a name of its own.');
			$values[$parameter->getName()] = $parameter->getValue();
		}

		return $values;
	}

	/**
	 * @param array<array-key, mixed> $criteria
	 * @return list<int>
	 */
	private function findIds(string $queryType, array $criteria): array
	{
		$queries = $this->createDoctrineQueries();

		return match ($queryType) {
			'objects' => array_map(
				static fn (Article $article): int => $article->getId(),
				$queries->objects->findBy(Article::class, $criteria, self::OrderById)->asArray(),
			),
			'arrays' => $this->extractIds($queries->arrays->findBy(Article::class, $criteria, self::OrderById)->asArray()),
			'scalars' => $this->extractIds($queries->scalars->findBy(Article::class, $criteria, self::OrderById)->asArray()),
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

	private function createQueriesWithHashtags(): DoctrineQueries
	{
		$this->persistFixtures();

		$em = $this->getEntityManager();
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'php'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'doctrine'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 2), 'php'));
		$em->flush();
		$em->clear();

		return $this->createDoctrineQueries();
	}

}
