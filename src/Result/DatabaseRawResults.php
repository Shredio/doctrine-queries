<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use Doctrine\DBAL\Result;

final readonly class DatabaseRawResults
{

	public function __construct(
		private Result $result,
	)
	{
	}

	/**
	 * @return iterable<int, array<string, mixed>>
	 */
	public function yield(bool $closeCursor = true): iterable
	{
		while (($row = $this->result->fetchAssociative()) !== false) {
			yield $row;
		}

		if ($closeCursor) {
			$this->result->free();
		}
	}

	/**
	 * @return iterable<int, list<mixed>>
	 */
	public function yieldAsList(bool $closeCursor = true): iterable
	{
		while (($row = $this->result->fetchNumeric()) !== false) {
			yield $row;
		}

		if ($closeCursor) {
			$this->result->free();
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function asArray(): array
	{
		return iterator_to_array($this->yield(), false);
	}

	/**
	 * @return array<int, list<mixed>>
	 */
	public function asList(): array
	{
		return iterator_to_array($this->yieldAsList(), false);
	}

}
