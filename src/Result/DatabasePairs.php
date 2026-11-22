<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use Doctrine\ORM\Query;
use Shredio\DoctrineQueries\Exception\InvalidResultKeyException;

/**
 * @template TKey
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
		private string $keyField,
		private bool $sameKeyAsValue = false,
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
	 *
	 * @throws InvalidResultKeyException When a key is not a string or an integer, e.g. NULL or an object. yield() accepts any key.
	 */
	public function asArray(): array
	{
		$values = [];

		foreach ($this->yield() as $key => $value) {
			if (!is_string($key) && !is_int($key)) {
				throw new InvalidResultKeyException($this->keyField, $key);
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
		$key = $this->sameKeyAsValue ? self::ValueColumn : self::KeyColumn;

		foreach (QueryResultIterator::iterate($this->query) as $row) {
			yield $row[$key] => $row[self::ValueColumn]; // @phpstan-ignore-line
		}
	}

}
