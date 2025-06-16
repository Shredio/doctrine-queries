<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries;

use Doctrine\Persistence\ManagerRegistry;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;

final readonly class DoctrineQueries
{

	public ObjectQueries $objects;

	public ArrayQueries $arrays;

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

}
