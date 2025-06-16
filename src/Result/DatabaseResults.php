<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use Doctrine\ORM\Query;

/**
 * @template T
 */
final readonly class DatabaseResults
{

	/**
	 * @param Query<int, T> $query
	 */
	public function __construct(
		private Query $query,
	)
	{
	}

	/**
	 * @return static<T>
	 */
	public function setLimit(int $limit): self
	{
		$this->query->setMaxResults($limit);

		return $this;
	}

	/**
	 * @return static<T>
	 */
	public function setOffset(int $offset): self
	{
		$this->query->setFirstResult($offset);

		return $this;
	}

	/**
	 * @return list<T>
	 */
	public function asArray(): array
	{
		/** @var list<T> */
		return $this->query->execute();
	}

	/**
	 * @return iterable<int, T>
	 */
	public function yield(): iterable
	{
		/** @var iterable<int, T> */
		return $this->query->toIterable(hydrationMode: $this->query->getHydrationMode());
	}

}
