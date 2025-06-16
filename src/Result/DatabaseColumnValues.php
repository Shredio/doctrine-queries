<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

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
		return iterator_to_array($this->yield(), false);
	}

	/**
	 * @return iterable<TValue>
	 */
	public function yield(): iterable
	{
		foreach ($this->query->toIterable(hydrationMode: $this->query->getHydrationMode()) as $row) {
			yield $row[self::Column]; // @phpstan-ignore offsetAccess.nonOffsetAccessible
		}
	}

}
