<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

/**
 * An array whose keys are all known, e.g. array{id: int, name?: string}.
 *
 * @template TType of object
 */
final readonly class ConstantArray
{

	/**
	 * @param list<ConstantArrayItem<TType>> $items
	 */
	public function __construct(
		public array $items,
	)
	{
	}

	/**
	 * Whether the array can be empty, e.g. [] or a shape built by conditions that can all be false.
	 */
	public function hasOnlyOptionalItems(): bool
	{
		foreach ($this->items as $item) {
			if (!$item->optional) {
				return false;
			}
		}

		return true;
	}

}
