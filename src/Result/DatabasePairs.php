<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use Doctrine\ORM\Query;

/**
 * @template TKey of array-key
 * @template TValue
 */
final readonly class DatabasePairs
{

	public const string KeyColumn = 'k';
	public const string ValueColumn = 'v';

	/**
	 * @param Query<int, array{ k: TKey, v: TValue }> $query
	 */
	public function __construct(
		private Query $query,
	)
	{
	}

	/**
	 * @return static<TKey, TValue>
	 */
	public function setLimit(int $limit): self
	{
		$this->query->setMaxResults($limit);

		return $this;
	}

	/**
	 * @return static<TKey, TValue>
	 */
	public function setOffset(int $offset): self
	{
		$this->query->setFirstResult($offset);

		return $this;
	}

	/**
	 * @return array<TKey, TValue>
	 */
	public function asArray(): array
	{
		return iterator_to_array($this->yield(), true);
	}

	/**
	 * @return iterable<TKey, TValue>
	 */
	public function yield(): iterable
	{
		foreach ($this->query->toIterable(hydrationMode: $this->query->getHydrationMode()) as $row) {
			yield $row[self::KeyColumn] => $row[self::ValueColumn]; // @phpstan-ignore-line
		}
	}

}
