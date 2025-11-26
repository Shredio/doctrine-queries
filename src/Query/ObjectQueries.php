<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Shredio\DoctrineQueries\Pagination\Pagination;
use Shredio\DoctrineQueries\Preload\SmartEntityPreloader;
use Shredio\DoctrineQueries\Result\DatabaseResults;
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
	 * @param list<string> $preload Associations to eager load
	 * @param ?Pagination $pagination Pagination settings (limit and offset)
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return DatabaseResults<T> Collection of entity objects
	 */
	public function findBy(
		string $entity,
		array $criteria = [],
		array $orderBy = [],
		array $preload = [],
		?Pagination $pagination = null,
		array|string $joinConfig = 'left',
	): DatabaseResults
	{
		/** @var Query<int, T> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, [], $pagination, $joinConfig)->getQuery();

		return new DatabaseResults($query, $this->createFetchCallback($query->getEntityManager(), $preload));
	}

	/**
	 * Finds a single entity by criteria and returns it as an object.
	 *
	 * @template T of object
	 * @param class-string<T> $entity The entity class to query
	 * @param array<string, mixed> $criteria Filtering criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy Sorting parameters
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig Join configurations (left is default)
	 * @return T|null The found entity object or null if not found
	 */
	public function findOneBy(
		string $entity,
		array $criteria = [],
		array $orderBy = [],
		array|string $joinConfig = 'left',
	): ?object
	{
		/** @var Query<int, T> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy, [], null, $joinConfig)->setMaxResults(1)->getQuery();

		/** @var T|null */
		return $query->getOneOrNullResult();
	}

	/**
	 * @param list<string> $preload
	 * @return (callable(list<object>): void)|null
	 */
	private function createFetchCallback(EntityManagerInterface $em, array $preload): ?callable
	{
		if ($preload === []) {
			return null;
		}

		return static function (array $objects) use ($em, $preload): void {
			SmartEntityPreloader::preload($em, $objects, $preload); // @phpstan-ignore argument.type ($objects is list<object>)
		};
	}

}
