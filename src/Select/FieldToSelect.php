<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Select;

use Shredio\DoctrineQueries\Metadata\FieldMetadata;

final readonly class FieldToSelect
{

	public function __construct(
		public FieldMetadata $metadata,
		public string $alias,
	)
	{
	}

}
