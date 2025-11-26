<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use Doctrine\ORM\Query;

/**
 * @template T
 */
final readonly class DatabaseResults
{

	/** @var (callable(list<T>): void)|null */
	private mixed $onFetch;

	/**
	 * @param Query<int, T> $query
	 * @param (callable(list<T>): void)|null $onFetch
	 */
	public function __construct(
		private Query $query,
		?callable $onFetch = null,
	)
	{
		$this->onFetch = $onFetch;
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
		if ($this->onFetch !== null) {
			/** @var list<T> $results */
			$results = $this->query->execute();
			($this->onFetch)($results);

			return $results;
		}

		/** @var list<T> */
		return $this->query->execute();
	}

	/**
	 * @return iterable<int, T>
	 */
	public function yield(): iterable
	{
		if ($this->onFetch !== null) {
			return $this->asArray();
		}

		/** @var iterable<int, T> */
		return $this->query->toIterable(hydrationMode: $this->query->getHydrationMode());
	}

}
