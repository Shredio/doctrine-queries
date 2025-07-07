<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use Doctrine\ORM\Query;
use LogicException;

/**
 * @template TKey
 * @template TValue of array<string, mixed>
 */
final readonly class DatabaseIndexedResults
{

	/**
	 * @param Query<int, TValue> $query
	 */
	public function __construct(
		private Query $query,
		private string $keyColumn,
		private bool $unsetKeyColumn = false,
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
		$values = [];

		foreach ($this->yield() as $key => $value) {
			if (!is_string($key) && !is_int($key)) {
				throw new LogicException(sprintf(
					'The key column "%s" must return a string or an integer, got "%s". Tip: use yield() method to avoid this error.',
					$this->keyColumn,
					get_debug_type($key),
				));
			}

			$values[$key] = $value;
		}

		/** @var array<TKey, TValue> */
		return $values;
	}

	/**
	 * @return iterable<TKey, TValue>
	 */
	public function yield(): iterable
	{
		/** @var TValue $item */
		foreach ($this->query->toIterable(hydrationMode: $this->query->getHydrationMode()) as $item) {
			$key = $item[$this->keyColumn];
			if ($this->unsetKeyColumn) {
				unset($item[$this->keyColumn]);
			}

			yield $key => $item;
		}
	}

}
