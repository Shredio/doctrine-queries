<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Select\Field;

/**
 * A criteria item of a field, e.g. 'age >' => 18.
 *
 * @template TType of object
 */
final readonly class CriteriaItem
{

	/**
	 * @param string $operator Normalized when supported, otherwise kept as it is
	 * @param TType $valueType
	 */
	public function __construct(
		public Field $field,
		public string $operator,
		public object $valueType,
	)
	{
	}

}
