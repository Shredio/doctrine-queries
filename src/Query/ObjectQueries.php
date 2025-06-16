<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Shredio\DoctrineQueries\Result\DatabaseResults;
use Doctrine\ORM\Query;

/**
 * @internal
 */
final readonly class ObjectQueries extends BaseQueries
{

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param array<string, mixed> $criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy
	 * @return DatabaseResults<T>
	 */
	public function findBy(string $entity, array $criteria = [], array $orderBy = []): DatabaseResults
	{
		/** @var Query<int, T> $query */
		$query = $this->createFindBy($entity, $criteria, $orderBy)->getQuery();

		return new DatabaseResults($query);
	}

}
