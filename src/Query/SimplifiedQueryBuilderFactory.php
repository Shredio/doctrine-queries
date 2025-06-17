<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Select\SelectParser;

/**
 * Factory for creating simplified Doctrine query builders.
 * 
 * Provides a high-level interface for creating QueryBuilder instances with
 * automatic criteria parsing, select field handling, and relation management.
 * 
 * @internal
 */
final readonly class SimplifiedQueryBuilderFactory
{

	public function __construct(
		private ManagerRegistry $managerRegistry,
		private SelectParser $selectParser = new SelectParser(),
	)
	{
	}

	/**
	 * Creates a new factory instance with a different select parser.
	 * 
	 * @param SelectParser $selectParser The new select parser to use
	 * @return self New factory instance
	 */
	public function withSelectParser(SelectParser $selectParser): self
	{
		return new self($this->managerRegistry, $selectParser);
	}

	/**
	 * Creates a new factory instance with relation handling configuration.
	 * 
	 * @param bool $withRelations Whether to include relations in queries
	 * @return self New factory instance
	 */
	public function withRelations(bool $withRelations): self
	{
		return new self($this->managerRegistry, $this->selectParser->withRequireRelations($withRelations));
	}

	/**
	 * Gets the metadata for a given entity class.
	 * 
	 * @template T of object
	 * @param class-string<T> $entity The entity class
	 * @return ClassMetadata<T> The entity metadata
	 */
	public function getMetadata(string $entity): ClassMetadata
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		return $em->getClassMetadata($entity);
	}


	/**
	 * Creates a query builder for the given entity with select, criteria, and ordering.
	 * 
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param string[] $select Fields to select (empty for all fields)
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param bool $distinct Whether to return distinct results
	 * @return QueryBuilder Configured query builder
	 */
	public function create(string $entity, array $select = [], array $criteria = [], array $orderBy = [], bool $distinct = false): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $em->getClassMetadata($entity);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, 'a');

		if ($select) {
			$qb->select($this->selectParser->getFromSelect($metadata, $select, 'a'));
		} else {
			$qb->select($this->selectParser->getForAll($metadata, 'a'));
		}

		if ($distinct) {
			$qb->distinct();
		}

		if ($criteria) {
			$this->applyCriteria($qb, $criteria, 'a');
		}

		if ($orderBy) {
			$this->applyOrderBy($qb, $orderBy);
		}

		return $qb;
	}

	/**
	 * Creates a query builder for counting entities by criteria.
	 * 
	 * @param class-string $entity The entity class to count
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @return QueryBuilder Query builder configured for counting
	 */
	public function createCount(string $entity, array $criteria = []): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $em->getClassMetadata($entity);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, 'a');

		$fieldNames = $metadata->getIdentifierFieldNames();

		if (count($fieldNames) === 1) {
			$qb->select(sprintf('COUNT(a.%s)', $fieldNames[0]));
		} else {
			$qb->select('COUNT(a)');
		}

		$this->applyCriteria($qb, $criteria, 'a');

		return $qb;
	}

	/**
	 * Applies filtering criteria to a query builder.
	 * 
	 * @param QueryBuilder $qb The query builder to modify
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param string $alias The alias to use for the entity
	 */
	private function applyCriteria(QueryBuilder $qb, array $criteria, string $alias): void
	{
		foreach (CriteriaParser::parse($criteria) as $parsed) {
			$qb->andWhere($alias . '.' . $parsed->getExpression());

			if ($parsed->parameterName) {
				$qb->setParameter($parsed->parameterName, $parsed->value);
			}
		}
	}

	/**
	 * Applies ordering parameters to a query builder.
	 * 
	 * @param QueryBuilder $qb The query builder to modify
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 */
	private function applyOrderBy(QueryBuilder $qb, array $orderBy): void
	{
		foreach ($orderBy as $field => $direction) {
			$qb->addOrderBy(sprintf('a.%s', $field), $direction);
		}
	}

}
