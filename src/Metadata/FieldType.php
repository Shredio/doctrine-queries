<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Metadata;

use BackedEnum;

final readonly class FieldType
{

	/**
	 * @param class-string<BackedEnum>|null $enumType
	 * @param bool $unsigned Whether the column is mapped with the option "unsigned", the databases with unsigned columns
	 *                       (MySQL, MariaDB) then store no negative number in it
	 */
	public function __construct(
		public string $type,
		public bool $nullable,
		public ?string $enumType,
		public bool $isNested,
		public bool $isPrimaryKey = false,
		public bool $unsigned = false,
	)
	{
	}

}
