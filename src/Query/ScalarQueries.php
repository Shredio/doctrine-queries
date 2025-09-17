<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Shredio\DoctrineQueries\Pagination\Pagination;
use Shredio\DoctrineQueries\Result\DatabaseColumnValues;
use Shredio\DoctrineQueries\Result\DatabaseIndexedResults;
use Shredio\DoctrineQueries\Result\DatabasePairs;
use Shredio\DoctrineQueries\Result\DatabaseResults;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Shredio\DoctrineQueries\Select\QueryType;
use Shredio\DoctrineQueries\Select\SelectParser;

/**
 * Query executor for returning scalar values from the database.
 * 
 * Provides database query functionality that returns primitive scalar values
 * (strings, numbers, booleans, null) using Doctrine's scalar hydration mode.
 *
 * Criteria examples:
 *     - ['id' => 1] - equals
 *     - ['name !=' => 'John'] - not equals
 *     - ['age >' => 18] - greater than
 *     - ['age >=' => 18] - greater than or equal
 *     - ['name LIKE' => '%john%'] - pattern matching
 *     - ['name NOT LIKE' => '%admin%'] - negative pattern matching
 *     - ['status' => null] - IS NULL
 *     - ['status !=' => null] - IS NOT NULL
 *     - ['id' => [1, 2, 3]] - IN clause
 *     - ['id !=' => [1, 2, 3]] - NOT IN clause
 *     - ['id >' => 1, 'status' => 'active'] - multiple criteria (AND)
 *     - etc.
 *
 * Sorting examples:
 *     - ['name' => 'ASC'] - sort by name ascending
 *     - ['createdAt' => 'DESC'] - sort by creation date descending
 *
 * Select examples:
 *    - ['id', 'name'] - select only id and name fields
 *    - ['name' => 'personName'] - select the name field and alias it as personName
 *    - ['*'] - select all entity fields (without relations)
 *    - ['**'] - select all entity fields including relations (relation identifiers only)
 *    - ['author.name'] - select related field from author relation
 *    - ['author.*'] - select all fields from author relation
 *    - ['author.**'] - select all fields from author relation including its relations
 *    - ['*', 'author.**' => 'author_'] - select all entity fields and all author fields with prefix
 *
 * Join configuration examples:
 *   - 'left' - use LEFT JOIN for all relations (default, includes null values)
 *   - 'inner' - use INNER JOIN for all relations (excludes null values)
 *   - ['author' => 'inner'] - use INNER JOIN for author relation, LEFT for others
 *   - ['author.role' => 'left'] - use LEFT JOIN for author.role relation
 * 
 * @phpstan-type ValueType scalar|null
 */
final readonly class ScalarQueries extends BaseQueries
{

	/**
	 * Hydration mode constant for scalar results
	 */
	private const int HydrationMode = AbstractQuery::HYDRATE_SCALAR;

	protected function getQueryType(): QueryType
	{
		return QueryType::Scalar;
	}

	/**
	 * Finds entities by criteria and returns scalar values.
	 * 
	 * @param class-string $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @param ?Pagination $pagination Pagination settings (limit and offset)
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabaseResults<array<string, ValueType>> Collection of scalar results
	 */
	public function findBy(
		string $entity,
		array $criteria = [],
		array $orderBy = [],
		array $select = [],
		?Pagination $pagination = null,
		array|string $joinConfig = 'left',
	): DatabaseResults
	{
		/** @var Query<int, array<string, ValueType>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select, $pagination, $joinConfig)->getQuery();

		return new DatabaseResults($query->setHydrationMode(self::HydrationMode));
	}

	/**
	 * Find a single entity by criteria and returns scalar values.
	 *
	 * @param class-string $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return array<string, ValueType>|null
	 */
	public function findOneBy(
		string $entity,
		array $criteria = [],
		array $orderBy = [],
		array $select = [],
		array|string $joinConfig = 'left',
	): ?array
	{
		/** @var Query<int, array<string, ValueType>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select, null, $joinConfig)->getQuery();
		$query->setHydrationMode(self::HydrationMode);
		$query->setMaxResults(1);

		/** @var array<string, ValueType>|null */
		return $query->getOneOrNullResult();
	}

	/**
	 * Find entities by criteria and returns scalar values, keys are the values from the $indexField.
	 *
	 * @param class-string $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabaseIndexedResults<mixed, array<string, ValueType>> Collection of scalar results
	 */
	public function findIndexedBy(
		string $entity,
		string $indexField,
		array $criteria = [],
		array $orderBy = [],
		array $select = [],
		array|string $joinConfig = 'left',
	): DatabaseIndexedResults
	{
		$unsetIndexField = false;

		if (isset($select[$indexField])) {
			$indexField = $select[$indexField];
		} else if ($select !== [] && !in_array($indexField, $select, true)) {
			$select[] = $indexField;
			$unsetIndexField = true;
		}

		/** @var Query<int, array<string, ValueType>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select, null, $joinConfig)->getQuery();

		return new DatabaseIndexedResults($query->setHydrationMode(self::HydrationMode), $indexField, $unsetIndexField);
	}

	/**
	 * Finds key-value pairs from entities and returns scalar values.
	 * 
	 * @param class-string $entity The entity class to query
	 * @param string $key The field to use as keys
	 * @param string $value The field to use as values
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabasePairs<array-key, ValueType> Key-value pairs collection
	 */
	public function findPairsBy(
		string $entity,
		string $key,
		string $value,
		array $criteria = [],
		array $orderBy = [],
		array|string $joinConfig = 'left',
	): DatabasePairs
	{
		/** @var Query<int, array{ k: array-key, v: ValueType }> $query */
		$query = $this->createFindPairsBy($entity, $key, $value, $criteria, $orderBy, $joinConfig)->getQuery();

		return new DatabasePairs($query->setHydrationMode(self::HydrationMode), $key === $value);
	}

	/**
	 * Fetches a specific field from the database based on the given entity, field, and criteria.
	 *
	 * @param class-string $entity The class of the entity to fetch the field from.
	 * @param string $field The specific field to retrieve from the entity.
	 * @param array<string, mixed> $criteria Optional criteria to filter the query.
	 * @param array<string, 'ASC'|'DESC'> $orderBy Optional ordering of the results.
	 * @param bool $distinct Whether to return distinct values. USE as a named argument.
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabaseColumnValues<ValueType>
	 */
	public function findColumnValuesBy(
		string $entity,
		string $field,
		array $criteria = [],
		array $orderBy = [],
		bool $distinct = false,
		array|string $joinConfig = 'left',
	): DatabaseColumnValues
	{
		/** @var Query<int, array{ v: ValueType }> $query */
		$query = $this->createFindColumnValues($entity, $field, $criteria, $orderBy, $distinct, $joinConfig)->getQuery();

		return new DatabaseColumnValues($query->setHydrationMode(self::HydrationMode));
	}

	/**
	 * Fetches a specific field from the database based on the given entity, field, and criteria.
	 *
	 * @param class-string $entity The class of the entity to fetch the field from.
	 * @param string $field The specific field to retrieve from the entity.
	 * @param array<string, mixed> $criteria Criteria to filter the query.
	 * @return ValueType
	 */
	public function findSingleColumnValueBy(string $entity, string $field, array $criteria): mixed
	{
		$query = $this->createFindSingleColumnValue($entity, $field, $criteria)->getQuery();
		$query->setHydrationMode(self::HydrationMode);
		/** @var list<array{ v: ValueType }> $values */
		$values = $query->execute();

		return $values ? $values[0][self::SingleColumnValueColumn] : null;
	}

}
