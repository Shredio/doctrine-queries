<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Shredio\DoctrineQueries\Result\DatabaseResults;
use Doctrine\ORM\Query;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * Query executor for returning entities as objects.
 * 
 * Provides database query functionality that returns entity objects
 * directly from the database using Doctrine's object hydration mode.
 *
 * Criteria examples:
 *     - ['id' => 1] - equals
 *     - ['name !=' => 'John'] - not equals
 *     - ['age >' => 18] - greater than
 *     - ['age >=' => 18] - greater than or equal
 *     - ['name LIKE' => '%john%'] - pattern matching
 *     - ['name NOT LIKE' => '%admin%'] - negative pattern matching
 *     - ['status' => null] - IS NULL
 *     - ['status !=' => null] - IS NOT NULL
 *     - ['id' => [1, 2, 3]] - IN clause
 *     - ['id !=' => [1, 2, 3]] - NOT IN clause
 *     - ['id >' => 1, 'status' => 'active'] - multiple criteria (AND)
 *     - etc.
 *
 *  Sorting examples:
 *     - ['name' => 'ASC'] - sort by name ascending
 *     - ['createdAt' => 'DESC'] - sort by creation date descending
 *
 *  Select examples:
 *    - ['id', 'name'] - select only id and name fields
 *    - ['name' => 'personName'] - select the name field and alias it as personName
 * 
 */
final readonly class ObjectQueries extends BaseQueries
{

	protected function getQueryType(): QueryType
	{
		return QueryType::Object;
	}

	/**
	 * Finds entities by criteria and returns them as objects.
	 * 
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @return DatabaseResults<T> Collection of entity objects
	 */
	public function findBy(string $entity, array $criteria = [], array $orderBy = []): DatabaseResults
	{
		/** @var Query<int, T> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy)->getQuery();

		return new DatabaseResults($query);
	}

	/**
	 * Finds a single entity by criteria and returns it as an object.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @return T|null The found entity object or null if not found
	 */
	public function findOneBy(string $entity, array $criteria = [], array $orderBy = []): ?object
	{
		/** @var Query<int, T> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy)->setMaxResults(1)->getQuery();

		/** @var T|null */
		return $query->getOneOrNullResult();
	}

}
