<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;

/**
 * @template TValue
 */
final readonly class DatabaseColumnValues
{

	public const string Column = 'v';

	/**
	 * @param Query<int, array{ v: TValue }> $query
	 */
	public function __construct(
		private Query $query,
	)
	{
	}

	/**
	 * @return static<TValue>
	 */
	public function setLimit(int $limit): self
	{
		$this->query->setMaxResults($limit);

		return $this;
	}

	/**
	 * @return static<TValue>
	 */
	public function setOffset(int $offset): self
	{
		$this->query->setFirstResult($offset);

		return $this;
	}

	/**
	 * @return list<TValue>
	 */
	public function asArray(): array
	{
		$hydrationMode = $this->query->getHydrationMode();
		if ($hydrationMode !== AbstractQuery::HYDRATE_SCALAR) {
			return iterator_to_array($this->yield(), false);
		}

		// Scalar hydration returns the database values as they are, so the whole column is fetched at once
		// instead of hydrating its rows one by one.
		try {
			/** @var list<TValue> */
			return $this->query->getSingleColumnResult();
		} finally {
			$this->query->setHydrationMode($hydrationMode); // getSingleColumnResult() switches to a mode yield() cannot iterate
		}
	}

	/**
	 * @return iterable<int, TValue>
	 */
	public function yield(): iterable
	{
		foreach (QueryResultIterator::iterate($this->query) as $row) {
			yield $row[self::Column];
		}
	}

}
