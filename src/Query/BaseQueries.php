<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\QueryBuilder;
use Shredio\DoctrineQueries\Exception\MultipleFieldSelectionException;
use Shredio\DoctrineQueries\Order\NullsLast;
use Shredio\DoctrineQueries\Pagination\Pagination;
use Shredio\DoctrineQueries\Result\DatabaseIndexedResults;
use Shredio\DoctrineQueries\Result\DatabasePairs;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;
use SortDirection;

/**
 * Base class for all query executors.
 * 
 * Provides common functionality for different query types including helper
 * methods for creating queries with various selection modes and criteria.
 * 
 * @internal
 */
abstract readonly class BaseQueries
{

	/**
	 * Column alias for value collections
	 */
	protected const string ColumnValuesColumn = 'v';
	
	/**
	 * Column alias for single value queries
	 */
	protected const string SingleColumnValueColumn = 'v';

	public function __construct(
		protected SimplifiedQueryBuilderFactory $queryBuilderFactory,
	)
	{
	}

	abstract protected function getQueryType(): QueryType;

	/**
	 * Creates a query builder for finding entities with optional relation handling.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param array<array-key, mixed> $criteria Filtering criteria.
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters Examples:
	 *   - ['name' => SortDirection::Ascending] - sort by name ascending
	 *   - ['createdAt' => SortDirection::Descending] - sort by creation date descending
	 *   - ['position' => new NullsLast(SortDirection::Ascending)] - sort by position ascending, NULL values last
	 * @param string[] $select Fields to select
	 * @param ?Pagination $pagination Pagination settings (limit and offset)
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return QueryBuilder Configured query builder
	 */
	protected function createFindBy(
		string $entity,
		array $criteria = [],
		array $orderBy = [],
		array $select = [],
		?Pagination $pagination = null,
		array|string $joinConfig = 'left',
	): QueryBuilder
	{
		$qb = $this->queryBuilderFactory->create(
			$entity,
			$select,
			$criteria,
			$orderBy,
			joinConfig: $joinConfig,
			queryType: $this->getQueryType(),
		);

		return self::applyPagination($qb, $pagination);
	}

	/**
	 * Ensures the index field is part of the selection and resolves the result column holding its value.
	 *
	 * @param class-string $entity
	 * @param string[] $select
	 * @return array{string[], string, bool} The selection, the key column and whether the key column must be removed from the rows
	 *
	 * @throws MultipleFieldSelectionException
	 */
	protected function prepareIndexedSelection(string $entity, string $indexField, array $select): array
	{
		self::assertSingleField($indexField);

		if (isset($select[$indexField])) { // ['field' => 'alias']
			return [$select, $select[$indexField], false];
		}

		foreach ($select as $key => $value) {
			if (is_int($key) && $value === $indexField) { // ['author.name'] is returned in the "name" column
				return [$select, (new Field($indexField))->name, false];
			}
		}

		if ($select === []) {
			if ($this->queryBuilderFactory->getMetadata($entity)->hasField($indexField)) {
				return [$select, $indexField, false]; // the default selection contains all root fields
			}

			$select = ['*']; // associations and related fields are not part of the default selection
		}

		$select[$indexField] = DatabaseIndexedResults::KeyColumn;

		return [$select, DatabaseIndexedResults::KeyColumn, true];
	}

	/**
	 * Creates a query builder for finding key-value pairs from entities.
	 * 
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param string $key The field to use as keys
	 * @param string $value The field to use as values
	 * @param array<array-key, mixed> $criteria Filtering criteria.
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return QueryBuilder Configured query builder for pairs
	 *
	 * @throws MultipleFieldSelectionException
	 */
	protected function createFindPairsBy(
		string $entity,
		string $key,
		string $value,
		array $criteria = [],
		array $orderBy = [],
		array|string $joinConfig = 'left',
	): QueryBuilder
	{
		self::assertSingleField($key);
		self::assertSingleField($value);

		return $this->queryBuilderFactory->create(
			$entity,
			[$key => DatabasePairs::KeyColumn, $value => DatabasePairs::ValueColumn],
			$criteria,
			$orderBy,
			joinConfig: $joinConfig,
			queryType: $this->getQueryType(),
		);
	}

	/**
	 * Creates a query builder for fetching multiple values from a specific field.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param string $field The field to retrieve values from
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters
	 * @param bool $distinct Whether to return distinct values
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return QueryBuilder Configured query builder for column values
	 *
	 * @throws MultipleFieldSelectionException
	 */
	protected function createFindColumnValues(
		string $entity,
		string $field,
		array $criteria = [],
		array $orderBy = [],
		bool $distinct = false,
		?Pagination $pagination = null,
		array|string $joinConfig = 'left',
	): QueryBuilder
	{
		self::assertSingleField($field);

		$qb = $this->queryBuilderFactory->create(
			$entity,
			[$field => self::ColumnValuesColumn],
			$criteria,
			$orderBy,
			$distinct,
			$joinConfig,
			queryType: $this->getQueryType(),
		);

		return self::applyPagination($qb, $pagination);
	}

	/**
	 * Creates a query builder for fetching a single value from a specific field.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param string $field The field to retrieve a value from
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters, they decide which row is the first one
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return QueryBuilder Configured query builder for single value (limited to 1 result)
	 *
	 * @throws MultipleFieldSelectionException
	 */
	protected function createFindSingleColumnValue(
		string $entity,
		string $field,
		array $criteria,
		array $orderBy = [],
		array|string $joinConfig = 'left',
	): QueryBuilder
	{
		self::assertSingleField($field);

		return $this->queryBuilderFactory->create(
			$entity,
			[$field => self::SingleColumnValueColumn],
			$criteria,
			$orderBy,
			joinConfig: $joinConfig,
			queryType: $this->getQueryType(),
		)->setMaxResults(1);
	}

	private static function applyPagination(QueryBuilder $qb, ?Pagination $pagination): QueryBuilder
	{
		if ($pagination?->limit !== null) {
			$qb->setMaxResults($pagination->limit);
		}

		if ($pagination?->offset !== null) {
			$qb->setFirstResult($pagination->offset);
		}

		return $qb;
	}

	/**
	 * A wildcard selects more columns than the single one the method reads its values from.
	 *
	 * @throws MultipleFieldSelectionException
	 */
	private static function assertSingleField(string $field): void
	{
		if (!(new Field($field))->getType()->isField()) {
			throw new MultipleFieldSelectionException(sprintf('Field "%s" is not a single field selection.', $field));
		}
	}

}
