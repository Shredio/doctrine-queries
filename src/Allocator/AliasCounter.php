<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Allocator;

final class AliasCounter
{

	private int $counter = 0;

	/**
	 * @return non-empty-string
	 */
	public function getNextAlias(): string
	{
		return sprintf('e%d', $this->counter++);
	}

}
