<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Criteria;

use InvalidArgumentException;

final readonly class Criteria
{

	private function __construct()
	{
		// Prevent instantiation
	}

	/**
	 * Matches the rows that match at least one of the alternatives (OR). The criteria of one alternative all have
	 * to match (AND), as the criteria of a query do. The result is passed among the criteria without a key:
	 *
	 *     ['published' => true, Criteria::anyOf(['author.name' => 'John'], ['title LIKE' => '%John%', 'featured' => true])]
	 *
	 * @template TAlternative of array<array-key, mixed>
	 * @param TAlternative ...$alternatives
	 * @return AnyOf<list<TAlternative>> The PHPStan extension returns the exact alternatives, AnyOf<array{TFirst, TSecond}>
	 *
	 * @throws InvalidArgumentException When there is no alternative or an alternative has no criteria
	 */
	public static function anyOf(array ...$alternatives): AnyOf
	{
		return new AnyOf(array_values($alternatives));
	}

}
