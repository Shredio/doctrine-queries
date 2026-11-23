<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

/**
 * @template TType of object
 */
final readonly class CallArgument
{

	/**
	 * @param TType $type
	 * @param string|null $name The name of a named argument
	 * @param bool $unpacked Whether the argument is unpacked, ...$values
	 */
	public function __construct(
		public object $type,
		public ?string $name = null,
		public bool $unpacked = false,
	)
	{
	}

}
