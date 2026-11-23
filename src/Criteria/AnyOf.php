<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Criteria;

use InvalidArgumentException;

/**
 * A group of criteria matched by the rows that match at least one of its alternatives, created by Criteria::anyOf().
 *
 * The type is not covariant on purpose. PHPStan would reduce a union of two groups to the one with fewer alternatives,
 * AnyOf<array{TFirst, TSecond}> is a subtype of AnyOf<array{TFirst}>, and the second alternative would not be validated.
 *
 * @template TAlternatives of list<array<array-key, mixed>>
 */
final readonly class AnyOf
{

	/** @var TAlternatives */
	public array $alternatives;

	/**
	 * @internal Use Criteria::anyOf().
	 *
	 * @param TAlternatives $alternatives
	 */
	public function __construct(array $alternatives)
	{
		$this->alternatives = $alternatives;

		if ($alternatives === []) {
			throw new InvalidArgumentException('Criteria::anyOf() needs at least one alternative.');
		}

		foreach ($alternatives as $index => $alternative) {
			if ($alternative === []) {
				// No criteria match every row, so would the whole group. It is rather a mistake than an intention.
				throw new InvalidArgumentException(sprintf('Alternative #%d of Criteria::anyOf() has no criteria.', $index + 1));
			}
		}
	}

}
