<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Shredio\DoctrineQueries\Order\NullsLast;
use Shredio\DoctrineQueries\Pagination\Pagination;
use Shredio\DoctrineQueries\Result\DatabaseColumnValues;
use Shredio\DoctrineQueries\Result\DatabaseIndexedResults;
use Shredio\DoctrineQueries\Result\DatabasePairs;
use Shredio\DoctrineQueries\Result\DatabaseResults;
use SortDirection;

/**
 * Queries returning the selected fields instead of entities, ArrayQueries and ScalarQueries differ only by the hydration.
 *
 * @template TValue Value of a selected field
 */
abstract readonly class FieldQueries extends BaseQueries
{

	/**
	 * @return AbstractQuery::HYDRATE_*
	 */
	abstract protected function getHydrationMode(): int;

	/**
	 * Finds entities by criteria and returns their selected fields.
	 *
	 * @param class-string $entity The entity class to query
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @param ?Pagination $pagination Pagination settings (limit and offset)
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabaseResults<array<string, TValue>> Collection of the rows
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
		/** @var Query<int, array<string, TValue>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select, $pagination, $joinConfig)->getQuery();

		return new DatabaseResults($query->setHydrationMode($this->getHydrationMode()));
	}

	/**
	 * Finds a single entity by criteria and returns its selected fields.
	 *
	 * @param class-string $entity The entity class to query
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return array<string, TValue>|null
	 */
	public function findOneBy(
		string $entity,
		array $criteria = [],
		array $orderBy = [],
		array $select = [],
		array|string $joinConfig = 'left',
	): ?array
	{
		/** @var Query<int, array<string, TValue>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select, null, $joinConfig)->getQuery();
		$query->setHydrationMode($this->getHydrationMode());
		$query->setMaxResults(1);

		/** @var array<string, TValue>|null */
		return $query->getOneOrNullResult();
	}

	/**
	 * Finds entities by criteria and returns their selected fields, keys are the values from the $indexField.
	 *
	 * @param class-string $entity The entity class to query
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabaseIndexedResults<mixed, array<string, TValue>> Collection of the rows
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
		[$select, $keyColumn, $unsetKeyColumn] = $this->prepareIndexedSelection($entity, $indexField, $select);

		/** @var Query<int, array<string, TValue>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select, null, $joinConfig)->getQuery();

		return new DatabaseIndexedResults($query->setHydrationMode($this->getHydrationMode()), $indexField, $keyColumn, $unsetKeyColumn);
	}

	/**
	 * Finds key-value pairs from entities.
	 *
	 * @param class-string $entity The entity class to query
	 * @param string $key The field to use as keys
	 * @param string $value The field to use as values
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabasePairs<array-key, TValue> Key-value pairs collection
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
		/** @var Query<int, array{ k: array-key, v: TValue }> $query */
		$query = $this->createFindPairsBy($entity, $key, $value, $criteria, $orderBy, $joinConfig)->getQuery();

		return new DatabasePairs($query->setHydrationMode($this->getHydrationMode()), $key, $key === $value);
	}

	/**
	 * Fetches the values of a field of the entities matching the criteria.
	 *
	 * @param class-string $entity The class of the entity to fetch the field from.
	 * @param string $field The specific field to retrieve from the entity.
	 * @param array<array-key, mixed> $criteria Optional criteria to filter the query.
	 * @param array<string, SortDirection|NullsLast> $orderBy Optional ordering of the results.
	 * @param bool $distinct Whether to return distinct values. USE as a named argument.
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabaseColumnValues<TValue>
	 */
	public function findColumnValuesBy(
		string $entity,
		string $field,
		array $criteria = [],
		array $orderBy = [],
		bool $distinct = false,
		?Pagination $pagination = null,
		array|string $joinConfig = 'left',
	): DatabaseColumnValues
	{
		/** @var Query<int, array{ v: TValue }> $query */
		$query = $this->createFindColumnValues($entity, $field, $criteria, $orderBy, $distinct, $pagination, $joinConfig)->getQuery();

		return new DatabaseColumnValues($query->setHydrationMode($this->getHydrationMode()));
	}

	/**
	 * Fetches the value of a field of the first entity matching the criteria, null when there is none.
	 *
	 * Null is returned for no entity as well as for a NULL value. To tell them apart, use
	 * findOneBy($entity, $criteria, select: [$field]), which returns null for no entity and [$field => null] for a NULL value.
	 *
	 * @param class-string $entity The class of the entity to fetch the field from.
	 * @param string $field The specific field to retrieve from the entity.
	 * @param array<array-key, mixed> $criteria Criteria to filter the query.
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters, they decide which entity is the first one
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return TValue|null
	 */
	public function findSingleColumnValueBy(
		string $entity,
		string $field,
		array $criteria,
		array $orderBy = [],
		array|string $joinConfig = 'left',
	): mixed
	{
		$query = $this->createFindSingleColumnValue($entity, $field, $criteria, $orderBy, $joinConfig)->getQuery();
		$query->setHydrationMode($this->getHydrationMode());
		/** @var list<array{ v: TValue }> $values */
		$values = $query->execute();

		return $values !== [] ? $values[0][self::SingleColumnValueColumn] : null;
	}

}
