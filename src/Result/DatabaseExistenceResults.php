<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use BackedEnum;
use InvalidArgumentException;

/**
 * @template T of array<string, scalar|null>
 */
final readonly class DatabaseExistenceResults
{

	private const string KeySeparator = "\0";

	/** Distinguishes NULL from an empty string in the index key. */
	private const string NullPlaceholder = "\1";

	/** @var array<string, true> */
	private array $index;

	/** @var list<string> */
	private array $parts;

	/**
	 * @param iterable<T> $results
	 */
	public function __construct(iterable $results)
	{
		[$this->index, $this->parts] = $this->createIndex($results);
	}

	/**
	 * @param T $values
	 */
	public function has(array $values): bool
	{
		if ($this->parts === []) {
			return false;
		}

		$keyParts = [];
		foreach ($this->parts as $part) {
			if (!array_key_exists($part, $values)) {
				throw new InvalidArgumentException(sprintf('Missing key "%s" in values array.', $part));
			}

			$keyParts[] = $values[$part];
		}

		return isset($this->index[self::createKey($keyParts)]);
	}

	/**
	 * @param iterable<T> $results
	 * @return array{ array<string, true>, list<string> }
	 */
	private function createIndex(iterable $results): array
	{
		$parts = [];
		$index = [];
		foreach ($results as $result) {
			if ($parts === []) {
				$parts = array_keys($result);
			}

			$index[self::createKey($result)] = true;
		}

		return [$index, $parts];
	}

	/**
	 * Values are compared loosely the same way the database does, e.g. 1 and "1" create the same key.
	 *
	 * @param array<mixed> $values
	 */
	private static function createKey(array $values): string
	{
		$normalized = [];
		foreach ($values as $value) {
			$normalized[] = match (true) {
				$value === null => self::NullPlaceholder,
				is_bool($value) => (string) (int) $value,
				$value instanceof BackedEnum => (string) $value->value,
				is_scalar($value) => (string) $value,
				default => throw new InvalidArgumentException(sprintf('Value of type %s cannot be compared, pass the database value instead.', get_debug_type($value))),
			};
		}

		return implode(self::KeySeparator, $normalized);
	}

}
