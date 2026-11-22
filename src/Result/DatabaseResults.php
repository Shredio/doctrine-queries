<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use Doctrine\ORM\Query;

/**
 * @template T
 */
final readonly class DatabaseResults
{

	/**
	 * Results passed to $onFetch at once by yield(). It is the batch EntityPreloader loads the associations of entities in
	 * (and a multiple of the one for collections), so a result of fewer entities is a single batch and a larger one
	 * takes no more queries than asArray().
	 */
	public const int DefaultFetchBatchSize = 1_000;

	/** @var (callable(list<T>): void)|null */
	private mixed $onFetch;

	/**
	 * @param Query<int, T> $query
	 * @param (callable(list<T>): void)|null $onFetch Called with the fetched results before they are returned, e.g. to preload their associations
	 * @param positive-int $fetchBatchSize
	 */
	public function __construct(
		private Query $query,
		?callable $onFetch = null,
		private int $fetchBatchSize = self::DefaultFetchBatchSize,
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
			return $this->yieldInBatches($this->onFetch);
		}

		/** @var iterable<int, T> */
		return QueryResultIterator::iterate($this->query);
	}

	/**
	 * Passes the results to $onFetch in batches, so that only a batch is held before it is returned.
	 *
	 * @param callable(list<T>): void $onFetch
	 * @return iterable<int, T>
	 */
	private function yieldInBatches(callable $onFetch): iterable
	{
		$batch = [];
		foreach (QueryResultIterator::iterate($this->query) as $result) {
			$batch[] = $result;
			if (count($batch) < $this->fetchBatchSize) {
				continue;
			}

			$onFetch($batch);
			foreach ($batch as $fetchedResult) {
				yield $fetchedResult; // "yield from" would repeat the keys of the batch, these keys continue across them
			}

			$batch = [];
		}

		if ($batch !== []) {
			$onFetch($batch);
			foreach ($batch as $fetchedResult) {
				yield $fetchedResult;
			}
		}
	}

}
