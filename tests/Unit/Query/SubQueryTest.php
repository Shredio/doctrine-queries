<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Order\NullsLast;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\Entity\Role;
use Tests\TestCase;
use Tests\Unit\Helpers;

final class SubQueryTest extends TestCase
{

	use DoctrineContext;
	use Helpers;

	private const array ByIdAscending = ['id' => SortDirection::Ascending];

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideQueryTypes(): iterable
	{
		yield 'objects' => ['objects'];
		yield 'arrays' => ['arrays'];
		yield 'scalars' => ['scalars'];
	}

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function provideSubQuerySelections(): iterable
	{
		$selections = [
			'default selection' => [],
			'identifier' => ['id'],
			'aliased identifier' => ['id' => 'authorId'],
		];

		foreach (self::provideQueryTypes() as [$queryType]) {
			foreach ($selections as $name => $select) {
				yield sprintf('%s (%s)', $name, $queryType) => [$queryType, $select];
			}
		}
	}

	/**
	 * DQL does not accept aliases in a sub-select, the sub-query has to select without them for every parent query type.
	 *
	 * @param list<string> $select
	 */
	#[DataProvider('provideSubQuerySelections')]
	public function testSubQueryInEveryQueryType(string $queryType, array $select): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$criteria = ['author' => $queries->subQuery(Author::class, ['name' => 'John Doe'], select: $select)];

		$ids = $this->assertSingleQuery(fn (): array => match ($queryType) {
			'objects' => array_map(static fn (Article $article): int => $article->getId(), $queries->objects->findBy(Article::class, $criteria, self::ByIdAscending)->asArray()),
			'arrays' => self::column($queries->arrays->findBy(Article::class, $criteria, self::ByIdAscending)->asArray(), 'id'),
			'scalars' => self::column($queries->scalars->findBy(Article::class, $criteria, self::ByIdAscending)->asArray(), 'id'),
		});

		$this->assertSame([1, 3], $ids);
	}

	public function testSubQuerySelectingRelatedFields(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		// Authors of articles without a symbol, selected by the related identifier and by the association itself.
		foreach (['author.id', 'author'] as $selectedField) {
			$names = $queries->scalars->findColumnValuesBy(Author::class, 'name', [
				'id' => $queries->subQuery(Article::class, ['symbol' => null], select: [$selectedField]),
			], self::ByIdAscending)->asArray();

			$this->assertSame(['John Doe', 'Jane Smith'], $names, $selectedField);
		}
	}

	public function testNotInSubQuery(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$ids = $queries->scalars->findColumnValuesBy(Article::class, 'id', [
			'author !=' => $queries->subQuery(Author::class, ['name' => 'John Doe']),
		])->asArray();

		$this->assertSame([2], $ids);
	}

	public function testParentAndSubQueryParametersAndJoinsDoNotClash(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$ids = $this->assertSingleQuery(fn (): array => $queries->scalars->findColumnValuesBy(Article::class, 'id', [
			'author.name LIKE' => 'J%',
			'author' => $queries->subQuery(Author::class, ['role.name' => 'Administrator', 'id >' => 0]),
			'title LIKE' => '%Article',
		], self::ByIdAscending)->asArray());

		$this->assertSame([2], $ids);
	}

	public function testNestedSubQuery(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$ids = $this->assertSingleQuery(fn (): array => $queries->scalars->findColumnValuesBy(Article::class, 'id', [
			'author' => $queries->subQuery(Author::class, [
				'role' => $queries->subQuery(Role::class, ['name' => 'Administrator']),
			]),
		])->asArray());

		$this->assertSame([2], $ids);
	}

	public function testSiblingSubQueries(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$ids = $this->assertSingleQuery(fn (): array => $queries->scalars->findColumnValuesBy(Article::class, 'id', [
			'author' => $queries->subQuery(Author::class, ['name LIKE' => 'J%']),
			'author !=' => $queries->subQuery(Author::class, ['role.name' => 'Administrator']),
		], self::ByIdAscending)->asArray());

		$this->assertSame([1, 3], $ids);
	}

	public function testSubQueryCanBeReused(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$subQuery = $queries->subQuery(Author::class, ['name' => 'John Doe']);

		$this->assertSame([1, 3], $queries->scalars->findColumnValuesBy(Article::class, 'id', ['author' => $subQuery], self::ByIdAscending)->asArray());
		$this->assertSame([3], $queries->scalars->findColumnValuesBy(Article::class, 'id', ['author' => $subQuery, 'id >' => 1], self::ByIdAscending)->asArray());
		$this->assertSame(
			[],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', ['author' => $subQuery, 'author !=' => $subQuery])->asArray(),
			'The same sub-query can be used twice in one query.',
		);
	}

	public function testSubQueryOnRelatedAssociation(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$ids = $this->assertSingleQuery(fn (): array => $queries->scalars->findColumnValuesBy(Article::class, 'id', [
			'author.role' => $queries->subQuery(Role::class, ['name' => 'Administrator']),
		])->asArray());

		$this->assertSame([2], $ids);
	}

	public function testSubQueryWithOrderBy(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$ids = $queries->scalars->findColumnValuesBy(Article::class, 'id', [
			'author' => $queries->subQuery(Author::class, ['id >' => 0], [
				'role.name' => new NullsLast(SortDirection::Ascending),
				'name' => SortDirection::Descending,
			]),
		], self::ByIdAscending)->asArray();

		$this->assertSame([1, 2, 3], $ids);
	}

	/**
	 * @return iterable<string, array{list<string>, int}>
	 */
	public static function provideSelectionsOfMoreColumns(): iterable
	{
		yield 'two fields' => [['id', 'name'], 2];
		yield 'wildcard' => [['*'], 2];
		yield 'wildcard with relations' => [['**'], 3];
	}

	/**
	 * @param list<string> $select
	 */
	#[DataProvider('provideSelectionsOfMoreColumns')]
	public function testSubQueryHasToSelectOneColumn(array $select, int $columnCount): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage(sprintf('Sub-query of Tests\\Entity\\Author selects %d columns, but the value it is compared with needs exactly one.', $columnCount));

		$queries->scalars->findBy(Article::class, ['author' => $queries->subQuery(Author::class, select: $select)]);
	}

	public function testSubQueryWithJoinConfig(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$withLeftJoin = $queries->scalars->findColumnValuesBy(Article::class, 'id', [
			'author' => $queries->subQuery(Author::class, ['role.id' => null], joinConfig: ['role' => 'left']),
		], self::ByIdAscending)->asArray();
		$withInnerJoin = $queries->scalars->findColumnValuesBy(Article::class, 'id', [
			'author' => $queries->subQuery(Author::class, ['role.id' => null], joinConfig: ['role' => 'inner']),
		], self::ByIdAscending)->asArray();

		$this->assertSame([1, 3], $withLeftJoin);
		$this->assertSame([], $withInnerJoin);
	}

	public function testSubQueryInCountAndExists(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$criteria = ['author' => $queries->subQuery(Author::class, ['name' => 'John Doe'])];

		$this->assertSame(2, $queries->countBy(Article::class, $criteria));
		$this->assertTrue($queries->existsBy(Article::class, $criteria));
		$this->assertFalse($queries->existsBy(Article::class, ['author' => $queries->subQuery(Author::class, ['name' => 'Nobody'])]));
	}

	public function testQueryBuilderAndQueryValues(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();
		$queryBuilder = $this->getEntityManager()->createQueryBuilder()
			->select('a.id')
			->from(Author::class, 'a')
			->where('a.name = :author')
			->setParameter('author', 'John Doe');

		$this->assertSame([1, 3], $queries->scalars->findColumnValuesBy(Article::class, 'id', ['author' => $queryBuilder], self::ByIdAscending)->asArray());
		$this->assertSame([1, 3], $queries->scalars->findColumnValuesBy(Article::class, 'id', ['author' => $queryBuilder->getQuery()], self::ByIdAscending)->asArray());
		$this->assertSame([2], $queries->scalars->findColumnValuesBy(Article::class, 'id', ['author !=' => $queryBuilder])->asArray());
	}

}
