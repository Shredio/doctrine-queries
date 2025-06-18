<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Field\EntityMetadata;
use Shredio\DoctrineQueries\Field\FieldPath;
use Shredio\DoctrineQueries\Hydration\HydrationType;
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
		HydrationType $hydrationType,
		array $select = [],
		array $criteria = [],
		array $orderBy = [],
		bool $distinct = false,
		?bool $withRelations = null,
	): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$entityMetadata = new EntityMetadata($this->managerRegistry, $entity);

		$qb = $em->createQueryBuilder();
		$qb->from($entityMetadata->entity, $entityMetadata->alias);

		if ($select) {
			$qb->select($this->selectParser->getFromSelect($entityMetadata, $select, $hydrationType));
		} else {
			$qb->select(
				$this->selectParser->getForAll(
					$entityMetadata,
					$hydrationType,
					$withRelations ?? $hydrationType->getDefaultValueForWithRelations(),
				),
			);
		}

		if ($distinct) {
			$qb->distinct();
		}

		if ($criteria) {
			$this->applyCriteria($qb, $criteria, $entityMetadata);
		}

		if ($orderBy) {
			$this->applyOrderBy($qb, $orderBy, $entityMetadata);
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

		$entityMetadata = new EntityMetadata($this->managerRegistry, $entity);

		$qb = $em->createQueryBuilder();
		$qb->from($entityMetadata->entity, $entityMetadata->alias);

		$primaryField = $entityMetadata->getSingleFieldName();
		if ($primaryField !== null) {
			$qb->select(sprintf('COUNT(%s.%s)', $entityMetadata->alias, $primaryField));
		} else {
			$qb->select(sprintf('COUNT(%s)', $entityMetadata->alias));
		}

		$this->applyCriteria($qb, $criteria, $entityMetadata);

		return $qb;
	}

	/**
	 * Creates a query builder for deleting entities by criteria.
	 * 
	 * @param class-string $entity The entity class to delete from
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @return QueryBuilder Query builder configured for deletion
	 */
	public function createDelete(string $entity, array $criteria = []): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$entityMetadata = new EntityMetadata($this->managerRegistry, $entity);

		$qb = $em->createQueryBuilder();
		$qb->delete($entityMetadata->entity, $entityMetadata->alias);

		$this->applyCriteria($qb, $criteria, $entityMetadata);

		return $qb;
	}

	/**
	 * Applies filtering criteria to a query builder.
	 * 
	 * @param QueryBuilder $qb The query builder to modify
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param EntityMetadata<object> $metadata Metadata for the entity
	 */
	private function applyCriteria(QueryBuilder $qb, array $criteria, EntityMetadata $metadata): void
	{
		foreach (CriteriaParser::parse($criteria) as $parsed) {
			$qb->andWhere($parsed->getExpression($metadata->createField($parsed->field)));

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
	 * @param EntityMetadata<object> $metadata Metadata for the entity
	 */
	private function applyOrderBy(QueryBuilder $qb, array $orderBy, EntityMetadata $metadata): void
	{
		foreach ($orderBy as $field => $direction) {
			$field = $metadata->createField(FieldPath::createFromString($field));

			$qb->addOrderBy($field->path, $direction);
		}
	}

}
