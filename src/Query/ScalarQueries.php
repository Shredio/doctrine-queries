<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Shredio\DoctrineQueries\Hydration\HydrationType;
use Shredio\DoctrineQueries\Result\DatabaseColumnValues;
use Shredio\DoctrineQueries\Result\DatabasePairs;
use Shredio\DoctrineQueries\Result\DatabaseResults;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
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
 *  Sorting examples:
 *     - ['name' => 'ASC'] - sort by name ascending
 *     - ['createdAt' => 'DESC'] - sort by creation date descending
 *
 *  Select examples:
 *    - ['id', 'name'] - select only id and name fields
 *    - ['name' => 'personName'] - select the name field and alias it as personName
 * 
 * @internal
 * @phpstan-type ValueType scalar|null
 */
final readonly class ScalarQueries extends BaseQueries
{

	/**
	 * Hydration mode constant for scalar results
	 */
	private const int HydrationMode = AbstractQuery::HYDRATE_SCALAR;

	protected function getHydrationType(): HydrationType
	{
		return HydrationType::Scalar;
	}

	/**
	 * Finds entities by criteria and returns scalar values.
	 * 
	 * @param class-string $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @return DatabaseResults<array<string, ValueType>> Collection of scalar results
	 */
	public function findBy(string $entity, array $criteria = [], array $orderBy = [], array $select = []): DatabaseResults
	{
		/** @var Query<int, array<string, ValueType>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select)->getQuery();

		return new DatabaseResults($query->setHydrationMode(self::HydrationMode));
	}

	/**
	 * Finds entities by criteria including relations and returns scalar values.
	 * 
	 * @param class-string $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @return DatabaseResults<array<string, ValueType>> Collection of scalar results
	 */
	public function findByWithRelations(string $entity, array $criteria = [], array $orderBy = [], array $select = []): DatabaseResults
	{
		/** @var Query<int, array<string, ValueType>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select, withRelations: true)->getQuery();

		return new DatabaseResults($query->setHydrationMode(self::HydrationMode));
	}

	/**
	 * Finds key-value pairs from entities and returns scalar values.
	 * 
	 * @param class-string $entity The entity class to query
	 * @param string $key The field to use as keys
	 * @param string $value The field to use as values
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @return DatabasePairs<array-key, ValueType> Key-value pairs collection
	 */
	public function findPairsBy(string $entity, string $key, string $value, array $criteria = [], array $orderBy = []): DatabasePairs
	{
		/** @var Query<int, array{ k: array-key, v: ValueType }> $query */
		$query = $this->createFindPairsBy($entity, $key, $value, $criteria, $orderBy)->getQuery();

		return new DatabasePairs($query->setHydrationMode(self::HydrationMode));
	}

	/**
	 * Fetches a specific field from the database based on the given entity, field, and criteria.
	 *
	 * @param class-string $entity The class of the entity to fetch the field from.
	 * @param string $field The specific field to retrieve from the entity.
	 * @param array<string, mixed> $criteria Optional criteria to filter the query.
	 * @param array<string, 'ASC'|'DESC'> $orderBy Optional ordering of the results.
	 * @param bool $distinct Whether to return distinct values. USE as a named argument.
	 * @return DatabaseColumnValues<ValueType>
	 */
	public function findColumnValuesBy(string $entity, string $field, array $criteria = [], array $orderBy = [], bool $distinct = false): DatabaseColumnValues
	{
		/** @var Query<int, array{ v: ValueType }> $query */
		$query = $this->createFindColumnValues($entity, $field, $criteria, $orderBy, $distinct)->getQuery();

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
