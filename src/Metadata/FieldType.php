<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Metadata;

use BackedEnum;

final readonly class FieldType
{

	/**
	 * @param class-string<BackedEnum>|null $enumType
	 */
	public function __construct(
		public string $type,
		public bool $nullable,
		public ?string $enumType,
		public bool $isNested,
		public bool $isPrimaryKey = false,
	)
	{
	}

}
