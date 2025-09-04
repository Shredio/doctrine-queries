<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use RuntimeException;

final class NonUniqueSelectAliasException extends RuntimeException
{

	public function __construct(
		public readonly string $alias,
	)
	{
		$message = sprintf('The alias "%s" is used more than once in the select statement. Please use unique aliases.', $this->alias);

		parent::__construct($message);
	}

}
