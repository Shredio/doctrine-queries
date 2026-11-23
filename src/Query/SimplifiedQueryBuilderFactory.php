<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use LogicException;
use Shredio\DoctrineQueries\Allocator\FieldAliasAllocator;
use Shredio\DoctrineQueries\Allocator\ParameterCounter;
use Shredio\DoctrineQueries\Criteria\AnyOf;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\Join\JoinParser;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Order\NullsLast;
use Shredio\DoctrineQueries\Parameter\DoctrineParameterAware;
use Shredio\DoctrineQueries\Result\QueryResultIterator;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;
use Shredio\DoctrineQueries\Select\SelectParser;
use SortDirection;
use Traversable;

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

	/**
	 * Values bound to one query of existsManyBy(). Doctrine looks a parameter up by scanning all of them, also while
	 * parsing DQL, so a query with many parameters is built and parsed in quadratic time. Chunks of the same size repeat
	 * the DQL, which is then parsed once thanks to the query cache, and stay below the placeholder limits of the
	 * databases (2100 in SQL Server).
	 */
	private const int MaxBoundValuesPerExistenceQuery = 500;

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
		$em = $this->getEntityManager($entity);

		return $em->getClassMetadata($entity);
	}

	/**
	 * Creates a raw query builder for the given entity without any additional configuration.
	 *
	 * @param class-string $entity The entity class to query
	 * @return QueryBuilder Raw query builder instance
	 */
	public function createRaw(string $entity): QueryBuilder
	{
		$em = $this->getEntityManager($entity);

		return $em->createQueryBuilder();
	}

	/**
	 * Creates a query builder for the given entity with select, criteria, and ordering.
	 * 
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param string[] $select Fields to select (empty for all fields)
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters
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
		$em = $this->getEntityManager($entity);

		$metadata = $this->createMetadata($em, $entity, $queryType);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, $metadata->getRootAlias());

		$selection = SelectParser::getForSelection($metadata, $select, $queryType);
		if ($queryType === QueryType::SubQuery && count($selection) !== 1) {
			throw new LogicException(sprintf(
				'Sub-query of %s selects %d columns, but the value it is compared with needs exactly one. Select a single field, e.g. select: [\'id\'].',
				$entity,
				count($selection),
			));
		}

		$qb->select($selection);

		if ($distinct) {
			$qb->distinct();
		}

		if ($criteria) {
			$this->applyCriteria($qb, $criteria, $metadata, $joinConfig, self::getRowLevelPaths($metadata, array_keys($orderBy)));
		}

		if ($orderBy) {
			$this->applyOrderBy($qb, $orderBy, $metadata);
		}

		$this->applyJoins($qb, $metadata, $joinConfig);

		if ($queryType !== QueryType::SubQuery && $metadata->hasToManyJoin()) {
			$qb->setHint(QueryResultIterator::MaterializeHint, true);
		}

		return $qb;
	}

	/**
	 * Creates a query builder for counting entities by criteria.
	 * 
	 * @param class-string $entity The entity class to count
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return QueryBuilder Query builder configured for counting
	 */
	public function createCount(
		string $entity,
		array $criteria = [],
		?string $field = null,
		bool $distinct = false,
		array|string $joinConfig = 'left',
	): QueryBuilder
	{
		$em = $this->getEntityManager($entity);

		$metadata = $this->createMetadata($em, $entity, QueryType::Scalar);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, $metadata->getRootAlias());

		// The counted field is resolved first, criteria on the to-many association it is reached through filter its rows.
		if ($field !== null) {
			$select = $metadata->getFieldToSelect(new Field($field));
			$path = $metadata->getPathForField($select->metadata->field);
		} else {
			$path = sprintf('%s.%s', $metadata->rootAlias, $metadata->getSingleIdentifierField(false));
		}

		$this->applyCriteria($qb, $criteria, $metadata, $joinConfig, self::getRowLevelPaths($metadata));

		if ($field === null) {
			// A to-many join multiplies the root rows, counting entities needs each of them only once.
			$distinct = $distinct || $metadata->hasToManyJoin();
		}

		$qb->select(sprintf('COUNT(%s%s)', $distinct ? 'DISTINCT ' : '', $path));

		$this->applyJoins($qb, $metadata, $joinConfig);

		return $qb;
	}

	/**
	 * Creates query builders selecting the given sets of values that exist, the values are split into chunks queried separately.
	 *
	 * @param class-string $entity The entity class to check
	 * @param iterable<array<string, mixed>> $values Set of values to check for existence
	 * @return list<QueryBuilder> Empty when there is nothing to check
	 */
	public function createExistsManyBy(string $entity, iterable $values): array
	{
		$em = $this->getEntityManager($entity);

		$rows = [];
		$fields = [];
		foreach ($values as $row) {
			if ($row === []) {
				continue; // an empty set of values has no condition to check
			}

			$rows[] = $row;
			foreach ($row as $field => $value) {
				if ((new Field($field))->hasParent()) {
					throw new LogicException(sprintf(
						'Cannot check the existence of %s by "%s", existsManyBy() does not join related entities. Use the fields and associations of the entity itself.',
						$entity,
						$field,
					));
				}

				$fields[$field] = true;
			}
		}

		if ($rows === []) {
			return [];
		}

		$metadata = $this->createMetadata($em, $entity, QueryType::Scalar);
		$fieldNames = array_keys($fields);
		// Also validates the field names before they are used in the conditions.
		$select = SelectParser::getForSelection($metadata, $fieldNames, QueryType::Scalar);

		if (count($fieldNames) === 1) {
			$queryBuilders = $this->createExistsManyByInList($em, $entity, $metadata, $select, $fieldNames[0], $rows);
			if ($queryBuilders !== null) {
				return $queryBuilders;
			}
		}

		$queryBuilders = [];
		$rowsPerQuery = max(1, intdiv(self::MaxBoundValuesPerExistenceQuery, count($fieldNames)));
		foreach (array_chunk($rows, $rowsPerQuery) as $chunk) {
			$qb = $this->createExistenceQueryBuilder($em, $entity, $metadata, $select);
			$parameters = $qb->getParameters();
			$rowConditions = [];
			$index = 0; // restarts in every chunk, chunks of the same shape have the same DQL

			foreach ($chunk as $row) {
				$fieldConditions = [];
				foreach ($row as $field => $value) {
					$path = sprintf('%s.%s', $metadata->rootAlias, $field);
					$index++;

					if ($value === null) {
						$fieldConditions[] = $qb->expr()->isNull($path); // "= NULL" never matches in SQL

						continue;
					}

					// QueryBuilder::setParameter() looks for an existing parameter of the name first, these names are unique.
					$parameters->add(new Parameter($parameterName = sprintf('param_%d', $index), $value));
					$fieldConditions[] = $qb->expr()->eq($path, ':' . $parameterName);
				}

				$rowConditions[] = $qb->expr()->andX(...$fieldConditions);
			}

			$qb->where($qb->expr()->orX(...$rowConditions));
			$queryBuilders[] = $qb;
		}

		return $queryBuilders;
	}

	/**
	 * Creates a query builder for deleting entities by criteria.
	 *
	 * DQL DELETE statements cannot join, so criteria on related entities (e.g. 'author.name') are rejected,
	 * filter by the association with a sub-query instead.
	 *
	 * @param class-string $entity The entity class to delete from
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @return QueryBuilder Query builder configured for deletion
	 */
	public function createDelete(string $entity, array $criteria = []): QueryBuilder
	{
		$em = $this->getEntityManager($entity);

		self::assertNoRelatedCriteria($entity, $criteria);

		$metadata = $this->createMetadata($em, $entity, QueryType::Scalar);

		$qb = $em->createQueryBuilder();
		$qb->delete($entity, $metadata->getRootAlias());

		$this->applyCriteria($qb, $criteria, $metadata);

		return $qb;
	}

	/**
	 * Applies filtering criteria to a query builder.
	 *
	 * Criteria reached through a to-many association are checked by an EXISTS sub-query, so that they filter the root
	 * entities without multiplying their rows. They are joined only when the selection or the ordering joins the
	 * association too, then they filter the rows of the related entities.
	 *
	 * @param QueryBuilder $qb The query builder to modify
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @param array<string, true> $rowLevelPaths Association paths joined by the selection or the ordering
	 */
	private function applyCriteria(
		QueryBuilder $qb,
		array $criteria,
		QueryMetadata $metadata,
		array|string $joinConfig = 'left',
		array $rowLevelPaths = [],
	): void
	{
		foreach ($this->createConditions($qb, $criteria, $metadata, $joinConfig, $rowLevelPaths, new ParameterCounter()) as $condition) {
			$qb->andWhere($condition);
		}
	}

	/**
	 * Creates the conditions of the criteria, which all have to match, and binds their parameters to the query builder.
	 *
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @param array<string, true> $rowLevelPaths Association paths joined by the selection or the ordering
	 * @param ParameterCounter $parameterCounter Shared by all the criteria of the query builder, also by the nested ones
	 * @return list<string> Each condition can be combined with the others by AND or OR as it is
	 */
	private function createConditions(
		QueryBuilder $qb,
		array $criteria,
		QueryMetadata $metadata,
		array|string $joinConfig,
		array $rowLevelPaths,
		ParameterCounter $parameterCounter,
	): array
	{
		$fieldCriteria = [];
		$criteriaByToManyPath = [];
		$anyOfGroups = [];
		foreach ($criteria as $criteriaKey => $value) {
			if (is_int($criteriaKey)) {
				if (!$value instanceof AnyOf) {
					throw new InvalidArgumentException(sprintf(
						'Criteria item %d has no field name, only Criteria::anyOf() can be passed without one, %s given.',
						$criteriaKey,
						get_debug_type($value),
					));
				}

				$anyOfGroups[] = $value;

				continue;
			}

			if ($value instanceof AnyOf) {
				throw new InvalidArgumentException(sprintf(
					'Criteria::anyOf() cannot be compared with "%s", pass it among the criteria without a key.',
					$criteriaKey,
				));
			}

			[$field] = CriteriaParser::parseSingleField($criteriaKey);
			$toManyPath = $metadata->getFirstToManyPath($field);
			if ($toManyPath === null || isset($rowLevelPaths[$toManyPath])) {
				$fieldCriteria[$criteriaKey] = $value;

				continue;
			}

			if ($value instanceof Traversable) {
				$value = iterator_to_array($value, false); // a generator could not be read twice, see acceptNull()
			}

			// Relative to the related entity, e.g. "hashtag LIKE" for "hashTags.hashtag LIKE".
			$criteriaByToManyPath[$toManyPath][substr($criteriaKey, strlen($toManyPath) + 1)] = $value;
		}

		$items = CriteriaParser::parse(
			$fieldCriteria,
			suffix: $metadata->getRootAlias(),
			queryMetadata: $metadata,
			parameterCounter: $parameterCounter,
		);

		$conditions = [];
		$parameters = $qb->getParameters();

		foreach ($items as $parsed) {
			$conditions[] = $parsed->getExpression($metadata);

			if ($parsed->parameterName) {
				if ($parsed->value instanceof DoctrineParameterAware) {
					$parameters->add($parsed->value->createDoctrineParameter($parsed->parameterName));
				} else if (is_array($parsed->value)) {
					$parameters->add(new Parameter($parsed->parameterName, $this->convertListToDatabaseValues($qb, $metadata, $parsed->field, $parsed->value)));
				} else {
					$parameters->add(new Parameter($parsed->parameterName, $parsed->value));
				}
			}

			if ($parsed->parameters) {
				foreach ($parsed->parameters as $parameter) {
					$parameters->add($parameter);
				}
			}
		}

		foreach ($criteriaByToManyPath as $toManyPath => $relatedCriteria) {
			$conditions[] = $this->createToManyCondition($qb, $metadata, $toManyPath, $relatedCriteria, $joinConfig);
		}

		foreach ($anyOfGroups as $anyOf) {
			$conditions[] = $this->createAnyOfCondition($qb, $anyOf, $metadata, $joinConfig, $rowLevelPaths, $parameterCounter);
		}

		return $conditions;
	}

	/**
	 * Creates "(<alternative> OR <alternative>)", the criteria of one alternative are combined by AND. A to-many association
	 * is checked by an EXISTS sub-query of each alternative, its related entity is not shared with the other alternatives
	 * or with the criteria around the group.
	 *
	 * @param AnyOf<list<array<array-key, mixed>>> $anyOf
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @param array<string, true> $rowLevelPaths Association paths joined by the selection or the ordering
	 */
	private function createAnyOfCondition(
		QueryBuilder $qb,
		AnyOf $anyOf,
		QueryMetadata $metadata,
		array|string $joinConfig,
		array $rowLevelPaths,
		ParameterCounter $parameterCounter,
	): string
	{
		$alternatives = [];
		foreach ($anyOf->alternatives as $alternative) {
			$conditions = $this->createConditions($qb, $alternative, $metadata, $joinConfig, $rowLevelPaths, $parameterCounter);

			$alternatives[] = match (count($conditions)) {
				// Criteria without a condition match every row, e.g. ['id !=' => []]. The group is not left out,
				// the other alternatives have bound their parameters already.
				0 => '1 = 1',
				1 => $conditions[0],
				default => sprintf('(%s)', implode(' AND ', $conditions)),
			};
		}

		return count($alternatives) === 1 ? $alternatives[0] : sprintf('(%s)', implode(' OR ', $alternatives));
	}

	/**
	 * Creates "EXISTS (SELECT 1 FROM Related r WHERE <r belongs to the parent> AND <criteria>)". All criteria of the association
	 * are checked in one sub-query, so they have to match the same related entity, as they would in a join.
	 *
	 * The sub-query selects from the related entity instead of joining the association, Doctrine refuses to iterate a query
	 * joining a to-many association anywhere, also in a sub-query.
	 *
	 * @param array<string, mixed> $relatedCriteria Criteria relative to the related entity
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 */
	private function createToManyCondition(
		QueryBuilder $qb,
		QueryMetadata $metadata,
		string $toManyPath,
		array $relatedCriteria,
		array|string $joinConfig,
	): string
	{
		$association = new Field($toManyPath);
		$parentPath = $association->getParent();
		$parentAlias = $parentPath === null ? $metadata->getRootAlias() : $metadata->getAliasForPath($parentPath);
		$mapping = $metadata->getFieldMetadata($association)->getAssociationMapping();

		$em = $qb->getEntityManager();
		$subQueryFactory = $this->withParentQueryMetadata($metadata);

		$subQueryMetadata = $subQueryFactory->createMetadata($em, $mapping->targetEntity, QueryType::SubQuery);
		$subQuery = self::createRelatedEntitiesQueryBuilder($em, $mapping, $subQueryMetadata->getRootAlias(), $parentAlias);
		$relatedJoinConfig = self::getRelativeJoinConfig($joinConfig, $toManyPath);
		$subQueryFactory->applyCriteria($subQuery, $relatedCriteria, $subQueryMetadata, $relatedJoinConfig);
		$subQueryFactory->applyJoins($subQuery, $subQueryMetadata, $relatedJoinConfig);

		$condition = sprintf('EXISTS (%s)', $subQuery->getDQL());

		if (self::isLeftJoined($joinConfig, $toManyPath) && self::acceptNull($relatedCriteria)) {
			// A left join of no related entity produces a row of NULLs, which the criteria accept,
			// e.g. ['hashTags.hashtag' => null] matches the articles without any hashtag.
			$noRelatedEntity = self::createRelatedEntitiesQueryBuilder(
				$em,
				$mapping,
				$subQueryFactory->createMetadata($em, $mapping->targetEntity, QueryType::SubQuery)->getRootAlias(),
				$parentAlias,
			);
			$condition = sprintf('(%s OR NOT EXISTS (%s))', $condition, $noRelatedEntity->getDQL());
		}

		$parameters = $qb->getParameters();
		foreach ($subQuery->getParameters() as $parameter) {
			$parameters->add($parameter);
		}

		return $condition;
	}

	/**
	 * DQL DELETE statements cannot join, also not for the criteria of an alternative of Criteria::anyOf().
	 *
	 * @param class-string $entity
	 * @param array<array-key, mixed> $criteria
	 */
	private static function assertNoRelatedCriteria(string $entity, array $criteria): void
	{
		foreach ($criteria as $criteriaKey => $value) {
			if (is_int($criteriaKey)) {
				foreach ($value instanceof AnyOf ? $value->alternatives : [] as $alternative) { // anything else is rejected by createConditions()
					self::assertNoRelatedCriteria($entity, $alternative);
				}

				continue;
			}

			[$field] = CriteriaParser::parseSingleField($criteriaKey);
			if ($field->hasParent()) {
				throw new LogicException(sprintf(
					'Cannot delete %s by criteria "%s", DQL DELETE does not support joins. Filter by the association with a sub-query instead, e.g. [\'%s\' => $queries->subQuery(...)].',
					$entity,
					$criteriaKey,
					strstr($field->selector, '.', true),
				));
			}
		}
	}

	/**
	 * Selects the entities related to the parent alias by the to-many association.
	 */
	private static function createRelatedEntitiesQueryBuilder(
		EntityManagerInterface $em,
		AssociationMapping $mapping,
		string $relatedAlias,
		string $parentAlias,
	): QueryBuilder
	{
		$qb = $em->createQueryBuilder();
		$qb->select('1');
		$qb->from($mapping->targetEntity, $relatedAlias);

		if ($mapping instanceof OneToManyAssociationMapping) {
			$qb->where(sprintf('%s.%s = %s', $relatedAlias, $mapping->mappedBy, $parentAlias));
		} else { // many-to-many, either side
			$qb->where(sprintf('%s MEMBER OF %s.%s', $relatedAlias, $parentAlias, $mapping->fieldName));
		}

		return $qb;
	}

	/**
	 * Whether a row of NULLs satisfies all the criteria, i.e. each of them is "= NULL" or "IN" a list containing NULL.
	 *
	 * @param array<string, mixed> $criteria
	 */
	private static function acceptNull(array $criteria): bool
	{
		foreach ($criteria as $criteriaKey => $value) {
			[, $operator] = CriteriaParser::parseSingleField($criteriaKey);
			if (CriteriaParser::normalizeOperator($operator) !== '=') {
				return false;
			}

			if ($value !== null && !(is_array($value) && in_array(null, $value, true))) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig
	 */
	private static function isLeftJoined(array|string $joinConfig, string $path): bool
	{
		if (is_string($joinConfig)) {
			return $joinConfig === 'left';
		}

		// An inner join of a nested association makes its parents inner too.
		return (JoinParser::parse($joinConfig)[$path] ?? 'left') === 'left';
	}

	/**
	 * Applies ordering parameters to a query builder.
	 * 
	 * @param QueryBuilder $qb The query builder to modify
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters
	 */
	private function applyOrderBy(QueryBuilder $qb, array $orderBy, QueryMetadata $metadata): void
	{
		foreach ($orderBy as $field => $direction) {
			$path = $metadata->getPathForField(new Field($field));
			if ($direction instanceof NullsLast) {
				$qb->addOrderBy(sprintf('CASE WHEN %s IS NULL THEN 1 ELSE 0 END', $path), SortDirection::Ascending);
				$direction = $direction->direction;
			}

			$qb->addOrderBy($path, $direction);
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
		return $this->getEntityManager($entity)->getConnection();
	}

	/**
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 */
	private function applyJoins(QueryBuilder $qb, QueryMetadata $metadata, array|string $joinConfig): void
	{
		$joins = $metadata->getJoins();

		if (is_string($joinConfig)) {
			self::assertJoinType($joinConfig);
			$joinFixedType = $joinConfig;
		} else if ($joinConfig === []) {
			$joinFixedType = 'left';
		} else {
			$joinFixedType = null;
			foreach ($joinConfig as $path => $joinType) {
				self::assertJoinType($joinType, $path);
			}

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

	/**
	 * Values of a single field are bound as one list parameter, so the DQL does not depend on the number of values.
	 *
	 * @param class-string $entity
	 * @param list<string> $select
	 * @param non-empty-list<array<string, mixed>> $rows
	 * @return list<QueryBuilder>|null Null when the values cannot be bound as a list
	 */
	private function createExistsManyByInList(
		EntityManagerInterface $em,
		string $entity,
		QueryMetadata $metadata,
		array $select,
		string $field,
		array $rows,
	): ?array
	{
		$listValues = [];
		$hasNull = false;
		foreach ($rows as $row) {
			$value = $row[$field];
			if ($value === null) {
				$hasNull = true;
			} else {
				$listValues[] = $value instanceof BackedEnum ? $value->value : $value;
			}
		}

		$listParameterType = self::getListParameterType($listValues);
		if ($listParameterType === null) {
			return null;
		}

		$path = sprintf('%s.%s', $metadata->rootAlias, $field);
		$queryBuilders = [];
		foreach (array_chunk($listValues, self::MaxBoundValuesPerExistenceQuery) as $chunk) {
			$qb = $this->createExistenceQueryBuilder($em, $entity, $metadata, $select);
			$qb->where(sprintf('%s IN (:param_1)', $path));
			$qb->getParameters()->add(new Parameter('param_1', $chunk, $listParameterType));
			$queryBuilders[] = $qb;
		}

		if ($hasNull) {
			$qb = $this->createExistenceQueryBuilder($em, $entity, $metadata, $select);
			$qb->where(sprintf('%s IS NULL', $path)); // "IN (NULL)" never matches in SQL
			$queryBuilders[] = $qb;
		}

		return $queryBuilders;
	}

	/**
	 * DBAL binds lists of integers and strings only, booleans, dates or entities need a parameter of their own type.
	 *
	 * @param list<mixed> $values
	 */
	private static function getListParameterType(array $values): ?ArrayParameterType
	{
		$isIntegerList = true;
		$isStringList = true;
		foreach ($values as $value) {
			$isIntegerList = $isIntegerList && is_int($value);
			$isStringList = $isStringList && is_string($value);
		}

		return match (true) {
			$isIntegerList => ArrayParameterType::INTEGER,
			$isStringList => ArrayParameterType::STRING,
			default => null,
		};
	}

	/**
	 * @param class-string $entity
	 * @param list<string> $select
	 */
	private function createExistenceQueryBuilder(EntityManagerInterface $em, string $entity, QueryMetadata $metadata, array $select): QueryBuilder
	{
		return $em->createQueryBuilder()
			->select($select)
			->from($entity, $metadata->rootAlias);
	}

	/**
	 * Paths of the associations joined so far (by the selection) and of the associations the ordering will join.
	 *
	 * @param list<string> $orderByFields
	 * @return array<string, true>
	 */
	private static function getRowLevelPaths(QueryMetadata $metadata, array $orderByFields = []): array
	{
		$paths = array_fill_keys(array_keys($metadata->getJoins()), true);

		foreach ($orderByFields as $orderByField) {
			$parent = (new Field($orderByField))->getParent();
			while ($parent !== null) {
				$paths[$parent] = true;
				$parent = (new Field($parent))->getParent();
			}
		}

		return $paths;
	}

	/**
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig
	 * @return array<string, 'left'|'inner'>|'left'|'inner' The configuration of the joins below the path, relative to it
	 */
	private static function getRelativeJoinConfig(array|string $joinConfig, string $path): array|string
	{
		if (is_string($joinConfig)) {
			return $joinConfig;
		}

		$prefix = sprintf('%s.', $path);
		$relativeJoinConfig = [];
		foreach ($joinConfig as $joinPath => $joinType) {
			if (str_starts_with($joinPath, $prefix)) {
				$relativeJoinConfig[substr($joinPath, strlen($prefix))] = $joinType;
			}
		}

		return $relativeJoinConfig;
	}

	/**
	 * The join types are only documented as 'left'|'inner', any other string would silently become an inner join.
	 */
	private static function assertJoinType(string $joinType, ?string $path = null): void
	{
		if ($joinType === 'left' || $joinType === 'inner') {
			return;
		}

		throw new InvalidArgumentException(sprintf(
			'Unsupported join type "%s"%s, use "left" or "inner".',
			$joinType,
			$path === null ? '' : sprintf(' for "%s"', $path),
		));
	}

	/**
	 * @param class-string $entity
	 *
	 * @throws NoClassMetadataException
	 */
	private function getEntityManager(string $entity): EntityManagerInterface
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		if (!$em instanceof EntityManagerInterface) {
			throw new NoClassMetadataException($entity);
		}

		return $em;
	}

	/**
	 * DBAL binds the items of a list as they are and only as integers or strings. Doctrine converts a single object
	 * by its type but not the objects in a list, so they are converted by the type of the column here.
	 *
	 * @param array<array-key, mixed> $values
	 * @return array<array-key, mixed>
	 */
	private function convertListToDatabaseValues(QueryBuilder $qb, QueryMetadata $metadata, Field $field, array $values): array
	{
		$type = null;

		foreach ($values as $key => $value) {
			if (!is_object($value) || $value instanceof BackedEnum) {
				continue; // enums are converted by Doctrine
			}

			if ($type === null) {
				$fieldMetadata = $metadata->getFieldMetadata($field);
				if ($fieldMetadata->isAssociation) {
					return $values; // entities are converted to their identifiers by Doctrine
				}

				$type = Type::getType($fieldMetadata->getFieldType()->type);
			}

			$values[$key] = self::convertToDatabaseValue($type, $value, $qb->getEntityManager()->getConnection()->getDatabasePlatform());
		}

		return $values;
	}

	private static function convertToDatabaseValue(Type $type, object $value, AbstractPlatform $platform): mixed
	{
		try {
			return $type->convertToDatabaseValue($value, $platform);
		} catch (ConversionException $exception) {
			if ($value instanceof DateTimeInterface && !$value instanceof DateTimeImmutable) {
				// Immutable date types accept only DateTimeImmutable, while Doctrine accepts a single DateTime for them too.
				return $type->convertToDatabaseValue(DateTimeImmutable::createFromInterface($value), $platform);
			}

			throw $exception;
		}
	}

}
