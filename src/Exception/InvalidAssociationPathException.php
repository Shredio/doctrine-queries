<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use RuntimeException;

final class InvalidAssociationPathException extends RuntimeException
{

	public function __construct(
		public readonly string $entity,
		public readonly string $path,
		public readonly string $fieldName,
	)
	{
		parent::__construct(sprintf('The association path `%s` in entity `%s` is invalid. The field `%s` is not an association.', $path, $entity, $fieldName));
	}

}
