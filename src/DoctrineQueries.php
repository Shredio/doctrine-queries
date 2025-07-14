<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\RawQueryBuilder;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Shredio\DoctrineQueries\Query\SubQuery;

/**
 * Main entry point for simplified Doctrine queries.
 * 
 * Provides a simplified interface for common database operations with automatic
 * query building and result handling. Offers three main query types: object,
 * array, and scalar queries.
 */
final readonly class DoctrineQueries
{

	/**
	 * Object-based queries that return entities as objects
	 */
	public ObjectQueries $objects;

	/**
	 * Array-based queries that return results as associative arrays
	 */
	public ArrayQueries $arrays;

	/**
	 * Scalar-based queries that return primitive values
	 */
	public ScalarQueries $scalars;

	private SimplifiedQueryBuilderFactory $queryBuilderFactory;

	public function __construct(ManagerRegistry $managerRegistry)
	{
		$this->queryBuilderFactory = new SimplifiedQueryBuilderFactory($managerRegistry);

		$this->objects = new ObjectQueries($this->queryBuilderFactory);
		$this->arrays = new ArrayQueries($this->queryBuilderFactory);
		$this->scalars = new ScalarQueries($this->queryBuilderFactory);
	}

	/**
	 * Determines if an entity exists in the database based on the given criteria.
	 *
	 * @param class-string $entity The class of the entity to check for existence.
	 * @param array<string, mixed> $criteria The criteria used to filter the query.
	 *
	 * @return bool True if an entity matching the criteria exists, otherwise false.
	 */
	public function existsBy(string $entity, array $criteria): bool
	{
		$qb = $this->queryBuilderFactory->create($entity, criteria: $criteria);
		$qb->select('1');
		$qb->setMaxResults(1);

		return (bool) $qb->getQuery()->getOneOrNullResult();
	}

	/**
	 * Counts entities by a set of criteria.
	 *
	 * @param class-string $entity
	 * @param array<string, mixed> $criteria
	 * @return int<0, max>
	 */
	public function countBy(string $entity, array $criteria = []): int
	{
		$qb = $this->queryBuilderFactory->createCount($entity, criteria: $criteria);

		/** @var int<0, max> */
		return (int) $qb->getQuery()->getSingleScalarResult();
	}

	/**
	 * Deletes entities by a set of criteria.
	 *
	 * @param class-string $entity
	 * @param array<string, mixed> $criteria
	 * @return int<0, max> Number of deleted entities
	 */
	public function deleteBy(string $entity, array $criteria = []): int
	{
		$qb = $this->queryBuilderFactory->createDelete($entity, criteria: $criteria);

		$result = $qb->getQuery()->execute();
		assert(is_int($result));

		/** @var int<0, max> */
		return $result;
	}

	/**
	 * @param class-string $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @return SubQuery Configured query builder for the entity
	 */
	public function subQuery(string $entity, array $criteria = [], array $orderBy = [], array $select = []): SubQuery
	{
		return new SubQuery(
			fn (string $alias): QueryBuilder => $this->queryBuilderFactory->create($entity, $select, $criteria, $orderBy, alias: $alias),
		);
	}

	/**
	 * @param class-string $entityForConnection
	 */
	public function createQueryFromFile(string $entityForConnection, string $filePath): RawQueryBuilder
	{
		return new RawQueryBuilder(
			$this->readFile($filePath),
			$this->queryBuilderFactory->getConnectionFor($entityForConnection),
		);
	}

	private function readFile(string $filePath): string
	{
		if (!is_file($filePath)) {
			throw new LogicException(sprintf(
				"File '%s' does not exist or is not a file.",
				$filePath,
			));
		}

		$content = @file_get_contents($filePath); // @ is escalated to exception
		if ($content === false) {
			throw new LogicException(sprintf(
				"Unable to read file '%s'.",
				$filePath,
			));
		}

		return $content;
	}

}
