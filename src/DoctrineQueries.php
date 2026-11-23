<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Shredio\DoctrineQueries\Aggregation\AggregateFunction;
use Shredio\DoctrineQueries\Exception\AggregationOverflowException;
use Shredio\DoctrineQueries\Exception\UnsupportedAggregationException;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Order\NullsLast;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\RawQueryBuilder;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Shredio\DoctrineQueries\Query\SubQuery;
use Shredio\DoctrineQueries\Result\DatabaseExistenceResults;
use Shredio\DoctrineQueries\Result\DatabasePairs;
use Shredio\DoctrineQueries\Select\QueryType;
use SortDirection;

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
	 * @param array<array-key, mixed> $criteria The criteria used to filter the query.
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
	 * Checks which of the given sets of values exist, in a single query (or a few for many sets) instead of one per set.
	 *
	 * Example: $existing = $queries->existsManyBy(Article::class, [['id' => 1], ['id' => 2]]); $existing->has(['id' => 1])
	 *
	 * @param class-string $entity The class of the entity to check for existence.
	 * @param iterable<array<string, mixed>> $values Sets of values of the fields and associations of the entity itself
	 *
	 * @return DatabaseExistenceResults<array<string, scalar|null>> Answers has() for each of the sets
	 */
	public function existsManyBy(string $entity, iterable $values): DatabaseExistenceResults
	{
		return new DatabaseExistenceResults(
			$this->fetchExistingValues($this->queryBuilderFactory->createExistsManyBy($entity, $values)),
		);
	}

	/**
	 * Counts entities by a set of criteria.
	 *
	 * @param class-string $entity
	 * @param array<array-key, mixed> $criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return int<0, max>
	 */
	public function countBy(
		string $entity,
		array $criteria = [],
		?string $field = null,
		bool $distinct = false,
		array|string $joinConfig = 'left',
	): int
	{
		$qb = $this->queryBuilderFactory->createCount($entity, $criteria, $field, $distinct, $joinConfig);

		/** @var int<0, max> */
		return (int) $qb->getQuery()->getSingleScalarResult();
	}

	/**
	 * Counts entities by a set of criteria for each value of a field, the keys are the database values of the field
	 * (the identifier for an association).
	 *
	 * A value no entity matches is missing in the result, not counted as 0: $counts[$key] ?? 0
	 *
	 * Example: $queries->countGroupedBy(Comment::class, 'parent', ['parent' => $commentIds])->asArray()
	 *
	 * @param class-string $entity
	 * @param string $groupBy The field or the to-one association to count by
	 * @param array<array-key, mixed> $criteria
	 * @param string|null $field Counts the values of the field without NULL instead of the entities, a group can then have the count 0
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabasePairs<array-key, int<0, max>>
	 */
	public function countGroupedBy(
		string $entity,
		string $groupBy,
		array $criteria = [],
		?string $field = null,
		bool $distinct = false,
		array|string $joinConfig = 'left',
	): DatabasePairs
	{
		$qb = $this->queryBuilderFactory->createCount($entity, $criteria, $field, $distinct, $joinConfig, $groupBy);

		/** @var Query<int, array{ k: array-key, v: int<0, max> }> $query */
		$query = $qb->getQuery();
		// Scalar hydration returns database values, objects of custom types or dates could not be keys.
		$query->setHydrationMode(AbstractQuery::HYDRATE_SCALAR);

		return new DatabasePairs($query, $groupBy, convertValue: self::toCount(...));
	}

	/**
	 * Sums the values of a field of the entities matching the criteria.
	 *
	 * The sum is returned by the kind of the field, on every database: an integer for an integer field, a numeric string
	 * for a decimal field and a float for a float field. Null is returned when no entity matches or all the values are NULL.
	 *
	 * @param class-string $entity
	 * @param string $field An integer, decimal or float field, also of a related entity ('items.price')
	 * @param array<array-key, mixed> $criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return int|float|numeric-string|null
	 *
	 * @throws AggregationOverflowException When the sum of an integer field does not fit into an integer
	 * @throws UnsupportedAggregationException When the field cannot be summed
	 */
	public function sumBy(string $entity, string $field, array $criteria = [], array|string $joinConfig = 'left'): int|float|string|null
	{
		/** @var int|float|numeric-string|null */
		return $this->aggregate(AggregateFunction::Sum, $entity, $field, $criteria, $joinConfig);
	}

	/**
	 * Returns the lowest value of a field of the entities matching the criteria, NULL values are skipped.
	 *
	 * The value is converted by the type of the field, e.g. to a date object or an enum. Null is returned when no entity
	 * matches or all the values are NULL.
	 *
	 * @param class-string $entity
	 * @param array<array-key, mixed> $criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 *
	 * @throws UnsupportedAggregationException When the field is an association
	 */
	public function minBy(string $entity, string $field, array $criteria = [], array|string $joinConfig = 'left'): mixed
	{
		return $this->aggregate(AggregateFunction::Min, $entity, $field, $criteria, $joinConfig);
	}

	/**
	 * Returns the highest value of a field of the entities matching the criteria, NULL values are skipped.
	 *
	 * The value is converted by the type of the field, e.g. to a date object or an enum. Null is returned when no entity
	 * matches or all the values are NULL.
	 *
	 * @param class-string $entity
	 * @param array<array-key, mixed> $criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 *
	 * @throws UnsupportedAggregationException When the field is an association
	 */
	public function maxBy(string $entity, string $field, array $criteria = [], array|string $joinConfig = 'left'): mixed
	{
		return $this->aggregate(AggregateFunction::Max, $entity, $field, $criteria, $joinConfig);
	}

	/**
	 * Sums the values of a field for each value of the $groupBy field, see sumBy() for the type of the sums
	 * and countGroupedBy() for the keys.
	 *
	 * A sum is null when all the values of its group are NULL. A value no entity matches is missing in the result.
	 *
	 * Example: $queries->sumGroupedBy(StatusDailyView::class, 'views', 'status', ['status' => $statusIds])->asArray()
	 *
	 * @param class-string $entity
	 * @param string $field An integer, decimal or float field
	 * @param string $groupBy The field or the to-one association to sum by
	 * @param array<array-key, mixed> $criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabasePairs<array-key, int|float|numeric-string|null>
	 *
	 * @throws UnsupportedAggregationException When the field cannot be summed
	 */
	public function sumGroupedBy(
		string $entity,
		string $field,
		string $groupBy,
		array $criteria = [],
		array|string $joinConfig = 'left',
	): DatabasePairs
	{
		/** @var DatabasePairs<array-key, int|float|numeric-string|null> */
		return $this->aggregateGrouped(AggregateFunction::Sum, $entity, $field, $groupBy, $criteria, $joinConfig);
	}

	/**
	 * Returns the lowest value of a field for each value of the $groupBy field, see minBy() for the values
	 * and countGroupedBy() for the keys.
	 *
	 * @param class-string $entity
	 * @param string $groupBy The field or the to-one association to aggregate by
	 * @param array<array-key, mixed> $criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabasePairs<array-key, mixed>
	 *
	 * @throws UnsupportedAggregationException When the field is an association
	 */
	public function minGroupedBy(
		string $entity,
		string $field,
		string $groupBy,
		array $criteria = [],
		array|string $joinConfig = 'left',
	): DatabasePairs
	{
		return $this->aggregateGrouped(AggregateFunction::Min, $entity, $field, $groupBy, $criteria, $joinConfig);
	}

	/**
	 * Returns the highest value of a field for each value of the $groupBy field, see maxBy() for the values
	 * and countGroupedBy() for the keys.
	 *
	 * @param class-string $entity
	 * @param string $groupBy The field or the to-one association to aggregate by
	 * @param array<array-key, mixed> $criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabasePairs<array-key, mixed>
	 *
	 * @throws UnsupportedAggregationException When the field is an association
	 */
	public function maxGroupedBy(
		string $entity,
		string $field,
		string $groupBy,
		array $criteria = [],
		array|string $joinConfig = 'left',
	): DatabasePairs
	{
		return $this->aggregateGrouped(AggregateFunction::Max, $entity, $field, $groupBy, $criteria, $joinConfig);
	}

	/**
	 * Deletes entities by a set of criteria.
	 *
	 * Criteria on related entities (e.g. 'author.name') are not supported because DQL DELETE cannot join,
	 * use a sub-query instead: ['author' => $queries->subQuery(Author::class, ['name' => 'John'], select: ['id'])]
	 *
	 * @param class-string $entity
	 * @param array<array-key, mixed> $criteria
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
	 * @param array<array-key, mixed> $criteria Filtering criteria
	 * @param array<string, SortDirection|NullsLast> $orderBy Sorting parameters
	 * @param string[] $select Fields to select
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return SubQuery Configured query builder for the entity
	 */
	public function subQuery(
		string $entity,
		array $criteria = [],
		array $orderBy = [],
		array $select = [],
		array|string $joinConfig = 'left',
	): SubQuery
	{
		return new SubQuery(
			fn (QueryMetadata $queryMetadata): QueryBuilder =>
				$this->queryBuilderFactory
					->withParentQueryMetadata($queryMetadata)
					->create($entity, $select, $criteria, $orderBy, joinConfig: $joinConfig, queryType: QueryType::SubQuery),
		);
	}

	/**
	 * Retrieves the last (maximum) ID of an entity.
	 *
	 * @param class-string $entity The entity class to query
	 * @return int<1,max>|null The last ID or null if no entities exist
	 */
	public function getLastId(string $entity): ?int
	{
		$qb = $this->queryBuilderFactory->createRaw($entity);
		$metadata = $this->queryBuilderFactory->getMetadata($entity);
		if (count($metadata->identifier) !== 1) {
			throw new LogicException(sprintf('Entity %s has composite or no ID.', $entity));
		}

		$idField = $metadata->identifier[0];
		$qb->select('MAX(e.' . $idField . ')');
		$qb->from($entity, 'e');
		$result = $qb->getQuery()->getSingleScalarResult();

		if ($result === null) {
			return null;
		}
		if (is_string($result)) {
			// A driver can return integers as strings, e.g. PDO with ATTR_STRINGIFY_FETCHES.
			$result = filter_var($result, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $result;
		}
		if (!is_int($result)) {
			throw new LogicException(sprintf('Entity %s has non-integer ID.', $entity));
		}
		if ($result < 1) {
			throw new LogicException(sprintf('Entity %s has invalid ID %d.', $entity, $result));
		}

		return $result;
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

	/**
	 * @param class-string $entity
	 * @param array<array-key, mixed> $criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig
	 */
	private function aggregate(AggregateFunction $function, string $entity, string $field, array $criteria, array|string $joinConfig): mixed
	{
		$aggregation = $this->queryBuilderFactory->createAggregation($entity, $function, $field, $criteria, $joinConfig);

		return $aggregation->converter->convert($aggregation->queryBuilder->getQuery()->getSingleScalarResult());
	}

	/**
	 * @param class-string $entity
	 * @param array<array-key, mixed> $criteria
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig
	 * @return DatabasePairs<array-key, mixed>
	 */
	private function aggregateGrouped(
		AggregateFunction $function,
		string $entity,
		string $field,
		string $groupBy,
		array $criteria,
		array|string $joinConfig,
	): DatabasePairs
	{
		$aggregation = $this->queryBuilderFactory->createAggregation($entity, $function, $field, $criteria, $joinConfig, $groupBy);

		/** @var Query<int, array{ k: array-key, v: mixed }> $query */
		$query = $aggregation->queryBuilder->getQuery();
		// Scalar hydration returns database values, objects of custom types or dates could not be keys.
		$query->setHydrationMode(AbstractQuery::HYDRATE_SCALAR);

		return new DatabasePairs($query, $groupBy, convertValue: $aggregation->converter->convert(...));
	}

	/**
	 * A driver can return integers as strings, e.g. PDO with ATTR_STRINGIFY_FETCHES.
	 *
	 * @return int<0, max>
	 */
	private static function toCount(mixed $count): int
	{
		if (!is_int($count) && !is_string($count)) {
			throw new LogicException(sprintf('COUNT() returned %s instead of an integer.', get_debug_type($count)));
		}

		return max(0, (int) $count);
	}

	/**
	 * @param list<QueryBuilder> $queryBuilders
	 * @return iterable<array<string, scalar|null>>
	 */
	private function fetchExistingValues(array $queryBuilders): iterable
	{
		foreach ($queryBuilders as $qb) {
			// Scalar hydration returns database values, objects of custom types or dates could not be indexed.
			/** @var iterable<array<string, scalar|null>> $rows */
			$rows = $qb->getQuery()->toIterable(hydrationMode: AbstractQuery::HYDRATE_SCALAR);
			yield from $rows;
		}
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
