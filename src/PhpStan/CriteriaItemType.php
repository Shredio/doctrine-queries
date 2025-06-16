<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use PHPStan\Type\Type;

/**
 * @api
 */
final readonly class CriteriaItemType
{

	public function __construct(
		public string $fieldName,
		public string $operator,
		public Type $valueType,
	)
	{
	}

}
