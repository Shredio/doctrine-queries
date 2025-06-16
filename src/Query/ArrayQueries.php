<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Shredio\DoctrineQueries\Result\DatabaseColumnValues;
use Shredio\DoctrineQueries\Result\DatabasePairs;
use Shredio\DoctrineQueries\Result\DatabaseResults;

/**
 * @internal
 * @phpstan-type ValueType mixed
 */
final readonly class ArrayQueries extends BaseQueries
{

	private const int HydrationMode = AbstractQuery::HYDRATE_ARRAY;

	/**
	 * @param class-string $entity
	 * @param array<string, mixed> $criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy
	 * @param string[] $select
	 * @return DatabaseResults<array<string, ValueType>>
	 */
	public function findBy(string $entity, array $criteria = [], array $orderBy = [], array $select = []): DatabaseResults
	{
		/** @var Query<int, array<string, ValueType>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select)->getQuery();

		return new DatabaseResults($query->setHydrationMode(self::HydrationMode));
	}

	/**
	 * @param class-string $entity
	 * @param array<string, mixed> $criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy
	 * @param string[] $select
	 * @return DatabaseResults<array<string, ValueType>>
	 */
	public function findByWithRelations(string $entity, array $criteria = [], array $orderBy = [], array $select = []): DatabaseResults
	{
		/** @var Query<int, array<string, ValueType>> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, $select, withRelations: true)->getQuery();

		return new DatabaseResults($query->setHydrationMode(self::HydrationMode));
	}

	/**
	 * @param class-string $entity
	 * @param array<string, mixed> $criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy
	 * @return DatabasePairs<array-key, ValueType>
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
