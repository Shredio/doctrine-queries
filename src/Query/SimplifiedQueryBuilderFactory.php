<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Shredio\DoctrineQueries\Allocator\FieldAliasAllocator;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Join\JoinParser;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;
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
		private ?QueryMetadata $parentQueryMetadata = null,
	)
	{
	}

	public function withParentQueryMetadata(QueryMetadata $queryMetadata): self
	{
		return new self($this->managerRegistry, $queryMetadata);
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
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return QueryBuilder Configured query builder
	 */
	public function create(
		string $entity,
		array $select = [],
		array $criteria = [],
		array $orderBy = [],
		bool $distinct = false,
		array|string $joinConfig = 'left',
		QueryType $queryType = QueryType::Object,
	): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $this->createMetadata($em, $entity, $queryType);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, $metadata->getRootAlias());

		$qb->select(SelectParser::getForSelection($metadata, $select, $queryType));

		if ($distinct) {
			$qb->distinct();
		}

		if ($criteria) {
			$this->applyCriteria($qb, $criteria, $metadata);
		}

		if ($orderBy) {
			$this->applyOrderBy($qb, $orderBy, $metadata);
		}

		$this->applyJoins($qb, $metadata, $joinConfig);

		return $qb;
	}

	/**
	 * Creates a query builder for counting entities by criteria.
	 * 
	 * @param class-string $entity The entity class to count
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return QueryBuilder Query builder configured for counting
	 */
	public function createCount(string $entity, array $criteria = [], array|string $joinConfig = 'left'): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $this->createMetadata($em, $entity, QueryType::Scalar);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, $metadata->getRootAlias());

		$fieldName = $metadata->getSingleIdentifierField(false);

		$qb->select(sprintf('COUNT(%s.%s)', $metadata->rootAlias, $fieldName));

		$this->applyCriteria($qb, $criteria, $metadata);
		$this->applyJoins($qb, $metadata, $joinConfig);

		return $qb;
	}

	/**
	 * Creates a query builder for counting entities by criteria.
	 *
	 * @param class-string $entity The entity class to count
	 * @param iterable<array<string, mixed>> $values Set of values to check for existence
	 * @return QueryBuilder Query builder configured for counting
	 */
	public function createExistsManyBy(string $entity, iterable $values): ?QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $this->createMetadata($em, $entity, QueryType::Scalar);

		$qb = $em->createQueryBuilder();
		$fields = [];
		$index = 0;
		foreach ($values as $row) {
			$and = [];
			foreach ($row as $field => $value) {
				$fields[$field] = true;
				$qb->setParameter($paramName = 'param_' . $index++, $value);

				$and[] = $qb->expr()->eq($metadata->rootAlias . '.' . $field, ':' . $paramName);
			}

			$qb->orWhere($qb->expr()->andX(...$and));
		}

		if ($index === 0) {
			return null;
		}

		$qb->from($entity, $metadata->rootAlias);
		$qb->select(SelectParser::getForSelection($metadata, array_keys($fields), QueryType::Scalar));
		return $qb;
	}

	/**
	 * Creates a query builder for deleting entities by criteria.
	 * 
	 * @param class-string $entity The entity class to delete from
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return QueryBuilder Query builder configured for deletion
	 */
	public function createDelete(string $entity, array $criteria = [], array|string $joinConfig = 'left'): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $this->createMetadata($em, $entity, QueryType::Scalar);

		$qb = $em->createQueryBuilder();
		$qb->delete($entity, $metadata->getRootAlias());

		$this->applyCriteria($qb, $criteria, $metadata);

		$this->applyJoins($qb, $metadata, $joinConfig);

		return $qb;
	}

	/**
	 * Applies filtering criteria to a query builder.
	 * 
	 * @param QueryBuilder $qb The query builder to modify
	 * @param array<string, mixed> $criteria Filtering criteria
	 */
	private function applyCriteria(
		QueryBuilder $qb,
		array $criteria,
		QueryMetadata $metadata,
	): void
	{
		$items = CriteriaParser::parse(
			$criteria,
			suffix: $metadata->getRootAlias(),
			queryMetadata: $metadata,
		);

		foreach ($items as $parsed) {
			$qb->andWhere($parsed->getExpression($metadata));

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
	private function applyOrderBy(QueryBuilder $qb, array $orderBy, QueryMetadata $metadata): void
	{
		foreach ($orderBy as $field => $direction) {
			$qb->addOrderBy($metadata->getPathForField(new Field($field)), $direction);
		}
	}

	/**
	 * @param class-string $entity
	 */
	private function createMetadata(EntityManagerInterface $em, string $entity, QueryType $queryType): QueryMetadata
	{
		if ($this->parentQueryMetadata) {
			$aliasAllocator = $this->parentQueryMetadata->createAliasAllocatorForChild();
		} else {
			$aliasAllocator = new FieldAliasAllocator();
		}

		return new QueryMetadata($em->getMetadataFactory(), $em->getClassMetadata($entity), $queryType, $aliasAllocator); // @phpstan-ignore argument.type
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

	/**
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 */
	private function applyJoins(QueryBuilder $qb, QueryMetadata $metadata, array|string $joinConfig): void
	{
		$joins = $metadata->getJoins();

		if (is_string($joinConfig)) {
			$joinFixedType = $joinConfig;
		} else if ($joinConfig === []) {
			$joinFixedType = 'left';
		} else {
			$joinFixedType = null;
			$joinConfig = JoinParser::parse($joinConfig);
		}

		foreach ($joins as $path => $alias) {
			$pos = strrpos($path, '.');

			if ($pos === false) {
				$parentAlias = $metadata->getRootAlias();
				$relation = $path;
			} else {
				$parentPath = substr($path, 0, $pos);
				if (!isset($joins[$parentPath])) {
					throw new LogicException(sprintf('Parent path "%s" for join "%s" not found.', $parentPath, $path));
				}

				$parentAlias = $joins[$parentPath];
				$relation = substr($path, $pos + 1);
			}

			if ($joinFixedType === null) {
				$joinType = $joinConfig[$path] ?? 'left';
			} else {
				$joinType = $joinFixedType;
			}

			if ($joinType === 'left') {
				$qb->leftJoin(sprintf('%s.%s', $parentAlias, $relation), $alias);
			} else {
				$qb->innerJoin(sprintf('%s.%s', $parentAlias, $relation), $alias);
			}
		}
	}

}
