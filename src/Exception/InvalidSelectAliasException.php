<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use InvalidArgumentException;

/**
 * The alias is written to the DQL as it is, anything else than an identifier would change the query.
 */
final class InvalidSelectAliasException extends InvalidArgumentException
{

	public function __construct(
		public readonly string $alias,
		public readonly string $fieldName,
	)
	{
		parent::__construct(sprintf(
			'The alias "%s" of the selected field "%s" is not a valid identifier, use letters, digits and underscores and do not start with a digit.',
			$this->alias,
			$this->fieldName,
		));
	}

}
