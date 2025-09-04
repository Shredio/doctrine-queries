<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use RuntimeException;

final class FieldNotExistsException extends RuntimeException
{

	public function __construct(
		public readonly string $fieldName,
		public readonly string $entityName,
	)
	{
		$message = sprintf('Field "%s" does not exist in entity "%s".', $this->fieldName, $this->entityName);

		parent::__construct($message);
	}

}
