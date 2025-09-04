<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use PHPStan\Type\Type;
use Shredio\DoctrineQueries\Select\Field;

/**
 * @api
 */
final readonly class CriteriaItemType
{

	public function __construct(
		public Field $field,
		public string $operator,
		public Type $valueType,
	)
	{
	}

}
