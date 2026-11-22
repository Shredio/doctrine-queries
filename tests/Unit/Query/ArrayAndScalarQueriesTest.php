<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\InvalidResultKeyException;
use Shredio\DoctrineQueries\Exception\MultipleFieldSelectionException;
use Shredio\DoctrineQueries\Exception\NonUniqueSelectAliasException;
use Shredio\DoctrineQueries\Order\NullsLast;
use Shredio\DoctrineQueries\Pagination\Pagination;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\Symbol;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\Entity\FavouritePrompt;
use Tests\Entity\OneToOneAssoc;
use Tests\Entity\OneToOnePrimary;
use Tests\Entity\Prompt;
use Tests\TestCase;
use Tests\Unit\Helpers;
use Throwable;

/**
 * Behaviour shared by ArrayQueries and ScalarQueries, checked on values that hydrate the same way in both.
 * Hydration of objects is covered by ArrayQueriesTest and ScalarQueriesTest.
 */
final class ArrayAndScalarQueriesTest extends TestCase
{

	use DoctrineContext;
	use Helpers;

	private const array ByIdAscending = ['id' => SortDirection::Ascending];

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideQueryTypes(): iterable
	{
		yield 'arrays' => ['arrays'];
		yield 'scalars' => ['scalars'];
	}

	/**
	 * @return iterable<string, array{string, Pagination, list<int>}>
	 */
	public static function providePagination(): iterable
	{
		$cases = [
			'limit and offset' => [new Pagination(2, 1), [2, 3]],
			'limit only' => [new Pagination(2), [1, 2]],
			'offset only' => [new Pagination(offset: 2), [3]],
			'offset past the end' => [new Pagination(1, 5), []],
			'no limit and no offset' => [new Pagination(), [1, 2, 3]],
		];

		foreach (self::provideQueryTypes() as [$queryType]) {
			foreach ($cases as $name => [$pagination, $expectedIds]) {
				yield sprintf('%s (%s)', $name, $queryType) => [$queryType, $pagination, $expectedIds];
			}
		}
	}

	/**
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('providePagination')]
	public function testFindByPagination(string $queryType, Pagination $pagination, array $expectedIds): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$rows = $this->assertSingleQuery(fn (): array => $queries->findBy(Article::class, orderBy: self::ByIdAscending, select: ['id'], pagination: $pagination)->asArray());

		$this->assertSame($expectedIds, self::column($rows, 'id'));
	}

	/**
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('providePagination')]
	public function testFindColumnValuesByPagination(string $queryType, Pagination $pagination, array $expectedIds): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$values = $this->assertSingleQuery(fn (): array => $queries->findColumnValuesBy(Article::class, 'id', orderBy: self::ByIdAscending, pagination: $pagination)->asArray());

		$this->assertSame($expectedIds, $values);
	}

	#[DataProvider('provideQueryTypes')]
	public function testPaginationIsAppliedAfterCriteria(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame([2], self::column($queries->findBy(Article::class, ['id >' => 1], self::ByIdAscending, select: ['id'], pagination: new Pagination(1))->asArray(), 'id'));
		$this->assertSame(['Another Article'], $queries->findColumnValuesBy(Article::class, 'title', ['id >' => 1], self::ByIdAscending, pagination: new Pagination(1))->asArray());
	}

	#[DataProvider('provideQueryTypes')]
	public function testResultsSetLimitAndOffset(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame(
			[['id' => 2]],
			$queries->findBy(Article::class, orderBy: self::ByIdAscending, select: ['id'])->setLimit(1)->setOffset(1)->asArray(),
		);
		$this->assertSame(
			[2],
			$queries->findColumnValuesBy(Article::class, 'id', orderBy: self::ByIdAscending)->setOffset(1)->setLimit(1)->asArray(),
		);
		$this->assertSame(
			[2 => 'Another Article'],
			$queries->findPairsBy(Article::class, 'id', 'title', orderBy: self::ByIdAscending)->setLimit(1)->setOffset(1)->asArray(),
		);
		$this->assertSame(
			['Another Article' => ['id' => 2]],
			$queries->findIndexedBy(Article::class, 'title', orderBy: self::ByIdAscending, select: ['id'])->setLimit(1)->setOffset(1)->asArray(),
		);
	}

	#[DataProvider('provideQueryTypes')]
	public function testYieldReturnsTheSameAsAsArray(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$results = $queries->findBy(Article::class, orderBy: self::ByIdAscending, select: ['id', 'title']);
		$this->assertSame($results->asArray(), iterator_to_array($results->yield()));

		$indexed = $queries->findIndexedBy(Article::class, 'id', select: ['title']);
		$this->assertSame($indexed->asArray(), iterator_to_array($indexed->yield()));

		$pairs = $queries->findPairsBy(Article::class, 'id', 'title');
		$this->assertSame($pairs->asArray(), iterator_to_array($pairs->yield()));

		$columnValues = $queries->findColumnValuesBy(Article::class, 'title');
		$this->assertSame($columnValues->asArray(), iterator_to_array($columnValues->yield(), false));
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindBySelect(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame([
			['id' => 1, 'title' => 'Sample Article'],
			['id' => 2, 'title' => 'Another Article'],
			['id' => 3, 'title' => 'Third Article'],
		], $this->assertSingleQuery(fn (): array => $queries->findBy(Article::class, select: ['id', 'title'])->asArray()));

		$this->assertSame([
			['articleId' => 1, 'articleTitle' => 'Sample Article'],
			['articleId' => 2, 'articleTitle' => 'Another Article'],
			['articleId' => 3, 'articleTitle' => 'Third Article'],
		], $queries->findBy(Article::class, select: ['id' => 'articleId', 'title' => 'articleTitle'])->asArray());
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindBySelectRelatedFields(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame([
			['id' => 1, 'name' => 'John Doe'],
			['id' => 2, 'name' => 'Jane Smith'],
			['id' => 3, 'name' => 'John Doe'],
		], $this->assertSingleQuery(fn (): array => $queries->findBy(Article::class, orderBy: self::ByIdAscending, select: ['id', 'author.name'])->asArray()));

		$this->assertSame([
			['id' => 1, 'authorId' => 1, 'role' => null],
			['id' => 2, 'authorId' => 2, 'role' => 'Administrator'],
			['id' => 3, 'authorId' => 1, 'role' => null],
		], $queries->findBy(Article::class, orderBy: self::ByIdAscending, select: ['id', 'author.id' => 'authorId', 'author.role.name' => 'role'])->asArray());
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindBySelectRelationWildcards(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame([
			['id' => 1, 'name' => 'John Doe'],
			['id' => 2, 'name' => 'Jane Smith'],
			['id' => 1, 'name' => 'John Doe'],
		], $this->assertSingleQuery(fn (): array => $queries->findBy(Article::class, orderBy: self::ByIdAscending, select: ['author.*'])->asArray()));

		$this->assertSame([
			['id' => 1, 'name' => 'John Doe', 'role' => null],
			['id' => 2, 'name' => 'Jane Smith', 'role' => 1],
			['id' => 1, 'name' => 'John Doe', 'role' => null],
		], $queries->findBy(Article::class, orderBy: self::ByIdAscending, select: ['author.**'])->asArray());

		$this->assertSame([
			['title' => 'Sample Article', 'author_id' => 1, 'author_name' => 'John Doe', 'role_id' => null, 'role_name' => null],
			['title' => 'Another Article', 'author_id' => 2, 'author_name' => 'Jane Smith', 'role_id' => 1, 'role_name' => 'Administrator'],
			['title' => 'Third Article', 'author_id' => 1, 'author_name' => 'John Doe', 'role_id' => null, 'role_name' => null],
		], $queries->findBy(Article::class, orderBy: self::ByIdAscending, select: ['title', 'author.*' => 'author_', 'author.role.*' => 'role_'])->asArray());
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindBySelectErrors(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertThrows(FieldNotExistsException::class, static fn () => $queries->findBy(Article::class, select: ['author.unknown']));
		$this->assertThrows(InvalidAssociationPathException::class, static fn () => $queries->findBy(Article::class, select: ['title.length']));
		$this->assertThrows(NonUniqueSelectAliasException::class, static fn () => $queries->findBy(Article::class, select: ['id', 'author.id']));
		$this->assertThrows(NonUniqueSelectAliasException::class, static fn () => $queries->findBy(Article::class, select: ['title' => 'text', 'content' => 'text']));
	}

	/**
	 * @return iterable<string, array{string, array<string, SortDirection|NullsLast>, list<int>}>
	 */
	public static function provideOrderBy(): iterable
	{
		$cases = [
			'ascending' => [['id' => SortDirection::Ascending], [1, 2, 3, 4]],
			'descending' => [['id' => SortDirection::Descending], [4, 3, 2, 1]],
			'by related field' => [['author.name' => SortDirection::Ascending, 'id' => SortDirection::Descending], [2, 4, 3, 1]],
			// SQLite sorts NULL first in ascending order, so articles 2 and 3 would lead without NullsLast.
			'nulls last ascending' => [['symbol' => new NullsLast(SortDirection::Ascending), 'id' => SortDirection::Ascending], [4, 1, 2, 3]],
			'nulls last descending' => [['symbol' => new NullsLast(SortDirection::Descending), 'id' => SortDirection::Ascending], [1, 4, 2, 3]],
			'nulls last on related field' => [['author.role.name' => new NullsLast(SortDirection::Ascending), 'id' => SortDirection::Descending], [2, 4, 3, 1]],
		];

		foreach (self::provideQueryTypes() as [$queryType]) {
			foreach ($cases as $name => [$orderBy, $expectedIds]) {
				yield sprintf('%s (%s)', $name, $queryType) => [$queryType, $orderBy, $expectedIds];
			}
		}
	}

	/**
	 * @param array<string, SortDirection|NullsLast> $orderBy
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('provideOrderBy')]
	public function testOrderBy(string $queryType, array $orderBy, array $expectedIds): void
	{
		$this->persistFixtures(static fn (Author $johnDoe): Article => new Article(4, 'Fourth Article', 'This is the fourth article.', $johnDoe, new Symbol('abc')));
		$queries = $this->getQueries($queryType);

		$this->assertSame($expectedIds, $this->assertSingleQuery(fn (): array => $queries->findColumnValuesBy(Article::class, 'id', orderBy: $orderBy)->asArray()));
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindOneBy(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame(['id' => 1, 'title' => 'Sample Article'], $this->assertSingleQuery(fn (): ?array => $queries->findOneBy(Article::class, ['id' => 1], select: ['id', 'title'])));
		$this->assertNull($this->assertSingleQuery(fn (): ?array => $queries->findOneBy(Article::class, ['id' => 999])));
		$this->assertSame(['id' => 3], $queries->findOneBy(Article::class, orderBy: ['id' => SortDirection::Descending], select: ['id']));
		$this->assertSame(['id' => 2, 'name' => 'Administrator'], $queries->findOneBy(Article::class, select: ['id', 'author.role.name'], joinConfig: 'inner'));
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindPairsBy(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame(
			[1 => 'Sample Article', 2 => 'Another Article', 3 => 'Third Article'],
			$this->assertSingleQuery(fn (): array => $queries->findPairsBy(Article::class, 'id', 'title')->asArray()),
		);
		$this->assertSame(
			['Sample Article' => 'Sample Article', 'Another Article' => 'Another Article', 'Third Article' => 'Third Article'],
			$queries->findPairsBy(Article::class, 'title', 'title')->asArray(),
			'The same field as the key and the value is selected only once.',
		);
		$this->assertSame(
			[3 => 'Third Article', 2 => 'Another Article'],
			$queries->findPairsBy(Article::class, 'id', 'title', ['id >' => 1], ['id' => SortDirection::Descending])->asArray(),
		);
		$this->assertSame(
			[1 => 'John Doe', 2 => 'Jane Smith', 3 => 'John Doe'],
			$queries->findPairsBy(Article::class, 'id', 'author.name', orderBy: self::ByIdAscending)->asArray(),
		);
		$this->assertSame(
			[1 => 3, 2 => 2],
			$queries->findPairsBy(Article::class, 'author', 'id', orderBy: self::ByIdAscending)->asArray(),
			'A later row with the same key overwrites the earlier one.',
		);
	}

	/**
	 * @return iterable<string, array{string, string, list<string>|array<string, string>, array<array-key, array<string, mixed>>}>
	 */
	public static function provideFindIndexedBy(): iterable
	{
		$cases = [
			'index field is not selected' => ['title', ['id'], [
				'Sample Article' => ['id' => 1],
				'Another Article' => ['id' => 2],
				'Third Article' => ['id' => 3],
			]],
			'index field is selected' => ['id', ['id', 'title'], [
				1 => ['id' => 1, 'title' => 'Sample Article'],
				2 => ['id' => 2, 'title' => 'Another Article'],
				3 => ['id' => 3, 'title' => 'Third Article'],
			]],
			'index field is aliased' => ['title', ['id', 'title' => 'heading'], [
				'Sample Article' => ['id' => 1, 'heading' => 'Sample Article'],
				'Another Article' => ['id' => 2, 'heading' => 'Another Article'],
				'Third Article' => ['id' => 3, 'heading' => 'Third Article'],
			]],
			'alias of another field equals the index field' => ['id', ['title' => 'id'], [
				1 => ['id' => 'Sample Article'],
				2 => ['id' => 'Another Article'],
				3 => ['id' => 'Third Article'],
			]],
			'related field is selected' => ['author.name', ['id', 'author.name'], [
				'John Doe' => ['id' => 3, 'name' => 'John Doe'],
				'Jane Smith' => ['id' => 2, 'name' => 'Jane Smith'],
			]],
			'related field is not selected' => ['author.name', ['id'], [
				'John Doe' => ['id' => 3],
				'Jane Smith' => ['id' => 2],
			]],
			'association is not selected' => ['author', ['title'], [
				1 => ['title' => 'Third Article'],
				2 => ['title' => 'Another Article'],
			]],
		];

		foreach (self::provideQueryTypes() as [$queryType]) {
			foreach ($cases as $name => [$indexField, $select, $expected]) {
				yield sprintf('%s (%s)', $name, $queryType) => [$queryType, $indexField, $select, $expected];
			}
		}
	}

	/**
	 * @param list<string>|array<string, string> $select
	 * @param array<array-key, array<string, mixed>> $expected
	 */
	#[DataProvider('provideFindIndexedBy')]
	public function testFindIndexedBy(string $queryType, string $indexField, array $select, array $expected): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame($expected, $this->assertSingleQuery(fn (): array => $queries->findIndexedBy(Article::class, $indexField, orderBy: self::ByIdAscending, select: $select)->asArray()));
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindIndexedByWithoutSelect(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);
		$rootFields = ['id', 'title', 'content', 'symbol', 'createdAt', 'type'];

		$byTitle = $queries->findIndexedBy(Article::class, 'title')->asArray();
		$this->assertSame(['Sample Article', 'Another Article', 'Third Article'], array_keys($byTitle));
		$this->assertSame($rootFields, array_keys($byTitle['Sample Article']));

		$byAuthor = $this->assertSingleQuery(fn (): array => $queries->findIndexedBy(Article::class, 'author', orderBy: self::ByIdAscending)->asArray());
		$this->assertSame([1, 2], array_keys($byAuthor));
		$this->assertSame($rootFields, array_keys($byAuthor[1]), 'The association added for indexing is not part of the rows.');
		$this->assertSame(3, $byAuthor[1]['id']);

		$byAuthorName = $queries->findIndexedBy(Article::class, 'author.name', orderBy: self::ByIdAscending)->asArray();
		$this->assertSame(['John Doe', 'Jane Smith'], array_keys($byAuthorName));
		$this->assertSame($rootFields, array_keys($byAuthorName['Jane Smith']));
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindColumnValuesBy(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame(['Sample Article', 'Another Article', 'Third Article'], $this->assertSingleQuery(fn (): array => $queries->findColumnValuesBy(Article::class, 'title')->asArray()));
		$this->assertSame([1, 2, 1], $queries->findColumnValuesBy(Article::class, 'author', orderBy: self::ByIdAscending)->asArray());
		$this->assertSame([1, 2], $queries->findColumnValuesBy(Article::class, 'author', orderBy: ['author' => SortDirection::Ascending], distinct: true)->asArray());
		$this->assertSame(['Jane Smith', 'John Doe'], $queries->findColumnValuesBy(Article::class, 'author.name', orderBy: ['author.name' => SortDirection::Ascending], distinct: true)->asArray());
		$this->assertSame(['Third Article'], $queries->findColumnValuesBy(Article::class, 'title', ['author.name' => 'John Doe', 'symbol' => null])->asArray());
	}

	/**
	 * @return iterable<string, array{string, array<string, 'left'|'inner'>|'left'|'inner', bool, list<int|null>}>
	 */
	public static function provideFindColumnValuesByJoinConfig(): iterable
	{
		$cases = [
			'left join keeps rows without a role' => [['author.role' => 'left'], false, [null, 1, null]],
			'left join by default' => [[], false, [null, 1, null]],
			'inner join for all relations' => ['inner', false, [1]],
			'inner join for the nested relation' => [['author.role' => 'inner'], false, [1]],
			'inner join of the nested relation wins over the left join of its parent' => [['author' => 'left', 'author.role' => 'inner'], false, [1]],
			'path the query does not join is ignored' => [['author.role' => 'left', 'hashTags' => 'inner'], false, [null, 1, null]],
			// DISTINCT can be ordered only by the selected column on MySQL and PostgreSQL, NULL goes first in both.
			'distinct left join' => [['author.role' => 'left'], true, [null, 1]],
		];

		foreach (self::provideQueryTypes() as [$queryType]) {
			foreach ($cases as $name => [$joinConfig, $distinct, $expected]) {
				yield sprintf('%s (%s)', $name, $queryType) => [$queryType, $joinConfig, $distinct, $expected];
			}
		}
	}

	/**
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig
	 * @param list<int|null> $expected
	 */
	#[DataProvider('provideFindColumnValuesByJoinConfig')]
	public function testFindColumnValuesByJoinConfig(string $queryType, array|string $joinConfig, bool $distinct, array $expected): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$orderBy = $distinct ? ['author.role.id' => SortDirection::Ascending] : self::ByIdAscending;
		$values = $this->assertSingleQuery(fn (): array => $queries->findColumnValuesBy(Article::class, 'author.role.id', orderBy: $orderBy, distinct: $distinct, joinConfig: $joinConfig)->asArray());

		$this->assertSame($expected, $values);
	}

	/**
	 * The default selection is the same as '*', also for an entity identified by an association.
	 */
	#[DataProvider('provideQueryTypes')]
	public function testSelectionOfEntityIdentifiedByAssociation(string $queryType): void
	{
		$em = $this->getEntityManager();
		$em->persist($prompt = new Prompt(1, 'First', 'First prompt'));
		$em->persist(new FavouritePrompt(10, $prompt));
		$em->flush();
		$em->clear();
		$queries = $this->getQueries($queryType);

		$this->assertSame([['account' => 10]], $queries->findBy(FavouritePrompt::class)->asArray());
		$this->assertSame([['account' => 10]], $queries->findBy(FavouritePrompt::class, select: ['*'])->asArray());
		$this->assertSame([['account' => 10, 'prompt' => 1]], $queries->findBy(FavouritePrompt::class, select: ['**'])->asArray());
		$this->assertSame(['account' => 10], $queries->findOneBy(FavouritePrompt::class));
		$this->assertSame([1 => ['account' => 10]], $queries->findIndexedBy(FavouritePrompt::class, 'prompt')->asArray());
	}

	/**
	 * '**' adds the identifiers of the associations stored in the table of the entity: many-to-one and the owning side of one-to-one.
	 */
	#[DataProvider('provideQueryTypes')]
	public function testWildcardWithRelationsSelectsOwningSideOfOneToOne(string $queryType): void
	{
		$em = $this->getEntityManager();
		$em->persist($assoc = new OneToOneAssoc(1, 'associated'));
		$em->flush();
		$em->persist(new OneToOnePrimary($assoc, 'primary'));
		$em->flush();
		$em->clear();
		$queries = $this->getQueries($queryType);

		$this->assertSame([['normalField' => 'primary', 'assoc' => $assoc->id]], $queries->findBy(OneToOnePrimary::class, select: ['**'])->asArray());
		$this->assertSame([['normalField' => 'primary']], $queries->findBy(OneToOnePrimary::class, select: ['*'])->asArray());
		$this->assertSame([['normalField' => 'primary']], $queries->findBy(OneToOnePrimary::class)->asArray());
		$this->assertSame(
			[['normalField' => 'primary', 'assoc' => $assoc->id, 'assoc_id' => $assoc->id, 'assoc_normalField' => 'associated', 'assoc_nullableField' => null]],
			$queries->findBy(OneToOnePrimary::class, select: ['**', 'assoc.**' => 'assoc_'])->asArray(),
		);
	}

	#[DataProvider('provideQueryTypes')]
	public function testUnsupportedJoinType(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		// Any string other than 'left' used to become an inner join.
		$this->assertThrowsWithMessage(
			InvalidArgumentException::class,
			'Unsupported join type "right", use "left" or "inner".',
			static fn () => $queries->findColumnValuesBy(Article::class, 'author.role.id', joinConfig: 'right'),
		);
		$this->assertThrowsWithMessage(
			InvalidArgumentException::class,
			'Unsupported join type "LEFT" for "author.role", use "left" or "inner".',
			static fn () => $queries->findBy(Article::class, select: ['author.role.id'], joinConfig: ['author.role' => 'LEFT']),
		);
		$this->assertThrowsWithMessage(
			InvalidArgumentException::class,
			'Unsupported join type "outer", use "left" or "inner".',
			fn () => $this->createDoctrineQueries()->countBy(Article::class, ['author.name' => 'John Doe'], joinConfig: 'outer'),
		);
	}

	/**
	 * @return iterable<string, array{string, callable(ArrayQueries|ScalarQueries): mixed, string}>
	 */
	public static function provideWildcardsAsSingleField(): iterable
	{
		$cases = [
			'column values of *' => [static fn (ArrayQueries|ScalarQueries $queries): mixed => $queries->findColumnValuesBy(Article::class, '*'), '*'],
			'column values of **' => [static fn (ArrayQueries|ScalarQueries $queries): mixed => $queries->findColumnValuesBy(Article::class, '**'), '**'],
			'column values of a relation wildcard' => [static fn (ArrayQueries|ScalarQueries $queries): mixed => $queries->findColumnValuesBy(Article::class, 'author.*'), 'author.*'],
			'single column value' => [static fn (ArrayQueries|ScalarQueries $queries): mixed => $queries->findSingleColumnValueBy(Article::class, '*', ['id' => 1]), '*'],
			'key of pairs' => [static fn (ArrayQueries|ScalarQueries $queries): mixed => $queries->findPairsBy(Article::class, '*', 'id'), '*'],
			'value of pairs' => [static fn (ArrayQueries|ScalarQueries $queries): mixed => $queries->findPairsBy(Article::class, 'id', 'author.**'), 'author.**'],
			'index field' => [static fn (ArrayQueries|ScalarQueries $queries): mixed => $queries->findIndexedBy(Article::class, '*', select: ['id']), '*'],
			'index field without select' => [static fn (ArrayQueries|ScalarQueries $queries): mixed => $queries->findIndexedBy(Article::class, 'author.*'), 'author.*'],
		];

		foreach (self::provideQueryTypes() as [$queryType]) {
			foreach ($cases as $name => [$call, $field]) {
				yield sprintf('%s (%s)', $name, $queryType) => [$queryType, $call, $field];
			}
		}
	}

	/**
	 * A wildcard selects more columns than the one the values are read from.
	 *
	 * @param callable(ArrayQueries|ScalarQueries): mixed $call
	 */
	#[DataProvider('provideWildcardsAsSingleField')]
	public function testWildcardIsNotASingleField(string $queryType, callable $call, string $field): void
	{
		$queries = $this->getQueries($queryType);

		$this->assertQueryCount(0, fn () => $this->assertThrowsWithMessage(
			MultipleFieldSelectionException::class,
			sprintf('Field "%s" is not a single field selection.', $field),
			static fn () => $call($queries),
		));
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindPairsByNullKey(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);
		$pairs = $queries->findPairsBy(Article::class, 'author.role.id', 'id', orderBy: self::ByIdAscending);

		$yielded = [];
		foreach ($pairs->yield() as $key => $value) {
			$yielded[] = [$key, $value];
		}

		$this->assertSame([[null, 1], [1, 2], [null, 3]], $yielded, 'yield() returns the NULL keys of the left join.');

		$this->assertThrowsWithMessage(
			InvalidResultKeyException::class,
			'The key "author.role.id" must be a string or an integer to be used as an array key, got null. Exclude the rows with the criteria [\'author.role.id !=\' => null], or iterate over yield().',
			static fn () => $pairs->asArray(),
		);

		$this->assertSame([1 => 2], $queries->findPairsBy(Article::class, 'author.role.id', 'id', ['author.role.id !=' => null])->asArray());
		$this->assertSame([1 => 2], $queries->findPairsBy(Article::class, 'author.role.id', 'id', joinConfig: 'inner')->asArray());
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindPairsByNullKeyOfTheSameFieldAsTheValue(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertThrowsWithMessage(
			InvalidResultKeyException::class,
			'The key "author.role.id" must be a string or an integer to be used as an array key, got null.',
			static fn () => $queries->findPairsBy(Article::class, 'author.role.id', 'author.role.id')->asArray(),
		);
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindIndexedByNullKey(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);
		$indexed = $queries->findIndexedBy(Article::class, 'author.role.id', orderBy: self::ByIdAscending, select: ['id']);

		$yielded = [];
		foreach ($indexed->yield() as $key => $row) {
			$yielded[] = [$key, $row];
		}

		$this->assertSame([[null, ['id' => 1]], [1, ['id' => 2]], [null, ['id' => 3]]], $yielded, 'yield() returns the NULL keys of the left join.');

		$this->assertThrowsWithMessage(
			InvalidResultKeyException::class,
			'The key "author.role.id" must be a string or an integer to be used as an array key, got null. Exclude the rows with the criteria [\'author.role.id !=\' => null], or iterate over yield().',
			static fn () => $indexed->asArray(),
		);

		$this->assertSame([1 => ['id' => 2]], $queries->findIndexedBy(Article::class, 'author.role.id', ['author.role.id !=' => null], select: ['id'])->asArray());
		$this->assertSame([1 => ['id' => 2]], $queries->findIndexedBy(Article::class, 'author.role.id', select: ['id'], joinConfig: 'inner')->asArray());
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindSingleColumnValueBy(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame('Sample Article', $this->assertSingleQuery(fn (): mixed => $queries->findSingleColumnValueBy(Article::class, 'title', ['id' => 1])));
		$this->assertNull($queries->findSingleColumnValueBy(Article::class, 'title', ['id' => 999]));
		$this->assertNull($queries->findSingleColumnValueBy(Article::class, 'symbol', ['id' => 2]), 'A NULL value cannot be told from no row.');
		$this->assertSame('Jane Smith', $queries->findSingleColumnValueBy(Article::class, 'author.name', ['id' => 2]));
		$this->assertSame(2, $queries->findSingleColumnValueBy(Article::class, 'author', ['id' => 2]));
		$this->assertSame('Administrator', $queries->findSingleColumnValueBy(Article::class, 'author.role.name', ['author.role.id !=' => null]));
	}

	#[DataProvider('provideQueryTypes')]
	public function testFindSingleColumnValueByOrderAndJoins(string $queryType): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries($queryType);

		$this->assertSame('Third Article', $queries->findSingleColumnValueBy(Article::class, 'title', [], orderBy: ['id' => SortDirection::Descending]));
		$this->assertSame(
			1,
			$queries->findSingleColumnValueBy(Article::class, 'id', [], orderBy: ['symbol' => new NullsLast(SortDirection::Ascending), 'id' => SortDirection::Descending]),
			'The only article with a symbol is the first one, the articles without it are ordered last.',
		);
		$this->assertNull(
			$queries->findSingleColumnValueBy(Article::class, 'author.role.name', [], orderBy: ['id' => SortDirection::Ascending]),
			'The first article has an author without a role.',
		);
		$this->assertSame(
			'Administrator',
			$queries->findSingleColumnValueBy(Article::class, 'author.role.name', [], orderBy: ['id' => SortDirection::Ascending], joinConfig: 'inner'),
			'The inner join skips the articles whose author has no role.',
		);
		$this->assertSame(
			'Administrator',
			$queries->findSingleColumnValueBy(Article::class, 'author.role.name', [], joinConfig: ['author.role' => 'inner']),
		);
	}

	private function getQueries(string $queryType): ArrayQueries|ScalarQueries
	{
		$queries = $this->createDoctrineQueries();

		return match ($queryType) {
			'arrays' => $queries->arrays,
			'scalars' => $queries->scalars,
		};
	}

	/**
	 * @param class-string<Throwable> $expectedException
	 * @param callable(): mixed $callback
	 */
	private function assertThrowsWithMessage(string $expectedException, string $expectedMessage, callable $callback): void
	{
		try {
			$callback();
		} catch (Throwable $exception) {
			$this->assertInstanceOf($expectedException, $exception);
			$this->assertStringContainsString($expectedMessage, $exception->getMessage());

			return;
		}

		$this->fail(sprintf('Expected %s to be thrown.', $expectedException));
	}

	/**
	 * @param class-string<Throwable> $expectedException
	 * @param callable(): mixed $callback
	 */
	private function assertThrows(string $expectedException, callable $callback): void
	{
		try {
			$callback();
		} catch (Throwable $exception) {
			$this->assertInstanceOf($expectedException, $exception);

			return;
		}

		$this->fail(sprintf('Expected %s to be thrown.', $expectedException));
	}

}
