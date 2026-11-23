<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Allocator;

/**
 * Numbers the parameters of one query. Criteria parsed separately, as the alternatives of Criteria::anyOf() are,
 * share it so that they do not bind two values to the same name.
 */
final class ParameterCounter
{

	/** @var int<0, max> */
	private int $counter = 0;

	/**
	 * @return positive-int
	 */
	public function getNextIndex(): int
	{
		return ++$this->counter;
	}

}
