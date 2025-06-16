<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\QueryBuilder;
use Shredio\DoctrineQueries\Result\DatabasePairs;

/**
 * @internal
 */
abstract readonly class BaseQueries
{

	protected const string ColumnValuesColumn = 'v';
	protected const string SingleColumnValueColumn = 'v';

	public function __construct(
		protected SimplifiedQueryBuilderFactory $queryBuilderFactory,
	)
	{
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param array<string, mixed> $criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy
	 * @param string[] $select
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
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param array<string, mixed> $criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy
	 */
	protected function createFindPairsBy(string $entity, string $key, string $value, array $criteria = [], array $orderBy = []): QueryBuilder
	{
		return $this->queryBuilderFactory->create($entity, [$key => DatabasePairs::KeyColumn, $value => DatabasePairs::ValueColumn], $criteria, $orderBy);
	}

	/**
	 * Fetches a specific field from the database based on the given entity, field, and criteria.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The class of the entity to fetch the field from.
	 * @param string $field The specific field to retrieve from the entity.
	 * @param array<string, mixed> $criteria Optional criteria to filter the query.
	 * @param array<string, 'ASC'|'DESC'> $orderBy Optional ordering of the results.
	 * @param bool $distinct Whether to return distinct values. USE as a named argument.
	 */
	protected function createFindColumnValues(string $entity, string $field, array $criteria = [], array $orderBy = [], bool $distinct = false): QueryBuilder
	{
		return $this->queryBuilderFactory->create($entity, [$field => self::ColumnValuesColumn], $criteria, $orderBy, $distinct);
	}

	/**
	 * Fetches a specific field from the database based on the given entity, field, and criteria.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The class of the entity to fetch the field from.
	 * @param string $field The specific field to retrieve from the entity.
	 * @param array<string, mixed> $criteria Criteria to filter the query.
	 */
	protected function createFindSingleColumnValue(string $entity, string $field, array $criteria): QueryBuilder
	{
		return $this->queryBuilderFactory->create($entity, [$field => self::SingleColumnValueColumn], $criteria)->setMaxResults(1);
	}

}
