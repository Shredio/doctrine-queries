<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use InvalidArgumentException;

/**
 * @template T of array<string, scalar|null>
 */
final readonly class DatabaseExistenceResults
{

	/** @var array<array-key, scalar|null> */
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
			$keyParts[] = $values[$part] ?? throw new InvalidArgumentException(sprintf('Missing key "%s" in values array.', $part));
		}

		$key = implode("\0", $keyParts);
		return isset($this->index[$key]);
	}

	/**
	 * @param iterable<T> $results
	 * @return array{ array<array-key, scalar|null>, list<string> }
	 */
	private function createIndex(iterable $results): array
	{
		$parts = [];
		$index = [];
		foreach ($results as $result) {
			if ($parts === []) {
				$parts = array_keys($result);
			}

			$index[implode("\0", $result)] = true;
		}

		return [$index, $parts];
	}

}
