<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

/**
 * Has methods of the same names as the queries, the rules must not check its calls.
 */
final class UnrelatedFinder
{

	/**
	 * @param array<string, mixed> $criteria
	 */
	public function findBy(string $entity, array $criteria = []): self
	{
		return $this;
	}

	public function findPairsBy(string $entity, string $key, string $value): self
	{
		return $this;
	}

	/**
	 * @return array<mixed>
	 */
	public function asArray(): array
	{
		return [];
	}

}
