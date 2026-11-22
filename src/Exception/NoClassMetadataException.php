<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use RuntimeException;

final class NoClassMetadataException extends RuntimeException
{

	public function __construct(
		public readonly string $entity,
	)
	{
		parent::__construct(sprintf('Class "%s" is not an entity managed by Doctrine.', $entity));
	}

}
