<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use LogicException;

/**
 * A to-many association has no value of its own to filter, select or order by, only the fields of the related entity have.
 */
final class ToManyAssociationException extends LogicException
{

	public function __construct(
		public readonly string $fieldName,
		public readonly string $entityName,
	)
	{
		parent::__construct(sprintf(
			'Field "%s" of entity "%s" is a to-many association, use a field of the related entity instead, e.g. "%s.<field>".',
			$this->fieldName,
			$this->entityName,
			$this->fieldName,
		));
	}

}
