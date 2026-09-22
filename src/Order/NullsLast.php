<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Order;

use SortDirection;

/**
 * Orders a field in the given direction and puts NULL values after all other values,
 * independently of the direction and of how the database platform sorts NULL.
 *
 * Example: ['position' => new NullsLast(SortDirection::Ascending)]
 */
final readonly class NullsLast
{

	public function __construct(
		public SortDirection $direction,
	)
	{
	}

}
