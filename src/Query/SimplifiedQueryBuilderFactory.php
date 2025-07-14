<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\DBAL\Connection;
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
	public function create(
		string $entity,
		array $select = [],
		array $criteria = [],
		array $orderBy = [],
		bool $distinct = false,
		string $alias = 'e0',
	): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $em->getClassMetadata($entity);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, $alias);

		if ($select) {
			$qb->select($this->selectParser->getFromSelect($metadata, $select, $alias));
		} else {
			$qb->select($this->selectParser->getForAll($metadata, $alias));
		}

		if ($distinct) {
			$qb->distinct();
		}

		if ($criteria) {
			$this->applyCriteria($qb, $criteria, $alias);
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
	public function createCount(string $entity, array $criteria = [], string $alias = 'e0'): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $em->getClassMetadata($entity);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, $alias);

		$fieldName = $metadata->getIdentifierFieldNames()[0] ?? null;
		if ($fieldName === null) {
			throw new \LogicException(sprintf(
				'Entity "%s" does not have an identifier field defined.',
				$entity
			));
		}

		$qb->select(sprintf('COUNT(%s.%s)', $alias, $fieldName));

		$this->applyCriteria($qb, $criteria, $alias);

		return $qb;
	}

	/**
	 * Creates a query builder for deleting entities by criteria.
	 * 
	 * @param class-string $entity The entity class to delete from
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @return QueryBuilder Query builder configured for deletion
	 */
	public function createDelete(string $entity, array $criteria = [], string $alias = 'e0'): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$qb = $em->createQueryBuilder();
		$qb->delete($entity, $alias);

		$this->applyCriteria($qb, $criteria, $alias);

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
		foreach (CriteriaParser::parse($criteria, $alias) as $parsed) {
			$qb->andWhere($alias . '.' . $parsed->getExpression());

			if ($parsed->parameterName) {
				$qb->setParameter($parsed->parameterName, $parsed->value);
			}

			if ($parsed->parameters) {
				$params = $qb->getParameters();

				foreach ($parsed->parameters as $parameter) {
					$params->add($parameter);
				}
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
			$qb->addOrderBy(sprintf('e0.%s', $field), $direction);
		}
	}

	/**
	 * Gets the database connection for a given entity class.
	 *
	 * @param class-string $entity The entity class to get the connection for
	 * @return Connection The database connection for the entity
	 */
	public function getConnectionFor(string $entity): Connection
	{
		$em = $this->managerRegistry->getManagerForClass($entity);

		assert($em instanceof EntityManagerInterface);

		return $em->getConnection();
	}

}
