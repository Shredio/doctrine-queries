<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\QueryBuilder;
use Shredio\DoctrineQueries\Result\DatabasePairs;

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

	/**
	 * Creates a query builder for finding entities with optional relation handling.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria.
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters Examples:
	 *   - ['name' => 'ASC'] - sort by name ascending
	 *   - ['createdAt' => 'DESC'] - sort by creation date descending
	 * @param string[] $select Fields to select
	 * @param bool|null $withRelations Whether to include relations (null for default)
	 * @return QueryBuilder Configured query builder
	 */
	protected function createFindBy(
		string $entity,
		array $criteria = [],
		array $orderBy = [],
		array $select = [],
		?bool $withRelations = null,
	): QueryBuilder
	{
		$qbf = $this->queryBuilderFactory;
		if ($withRelations !== null) {
			$qbf = $this->queryBuilderFactory->withRelations($withRelations);
		}

		return $qbf->create($entity, $select, $criteria, $orderBy);
	}

	/**
	 * Creates a query builder for finding key-value pairs from entities.
	 * 
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param string $key The field to use as keys
	 * @param string $value The field to use as values
	 * @param array<string, mixed> $criteria Filtering criteria.
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @return QueryBuilder Configured query builder for pairs
	 */
	protected function createFindPairsBy(string $entity, string $key, string $value, array $criteria = [], array $orderBy = []): QueryBuilder
	{
		return $this->queryBuilderFactory->create($entity, [$key => DatabasePairs::KeyColumn, $value => DatabasePairs::ValueColumn], $criteria, $orderBy);
	}

	/**
	 * Creates a query builder for fetching multiple values from a specific field.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param string $field The field to retrieve values from
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param bool $distinct Whether to return distinct values
	 * @return QueryBuilder Configured query builder for column values
	 */
	protected function createFindColumnValues(string $entity, string $field, array $criteria = [], array $orderBy = [], bool $distinct = false): QueryBuilder
	{
		return $this->queryBuilderFactory->create($entity, [$field => self::ColumnValuesColumn], $criteria, $orderBy, $distinct);
	}

	/**
	 * Creates a query builder for fetching a single value from a specific field.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param string $field The field to retrieve a value from
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @return QueryBuilder Configured query builder for single value (limited to 1 result)
	 */
	protected function createFindSingleColumnValue(string $entity, string $field, array $criteria): QueryBuilder
	{
		return $this->queryBuilderFactory->create($entity, [$field => self::SingleColumnValueColumn], $criteria)->setMaxResults(1);
	}

}
