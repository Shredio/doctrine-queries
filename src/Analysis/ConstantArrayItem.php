<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

/**
 * @template TType of object
 */
final readonly class ConstantArrayItem
{

	/**
	 * @param TType $valueType
	 */
	public function __construct(
		public int|string $key,
		public object $valueType,
		public bool $optional = false,
	)
	{
	}

}
