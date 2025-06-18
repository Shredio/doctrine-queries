<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Field;

final readonly class MappedFieldPath
{

	public string $path;

	/**
	 * @param class-string $entity
	 */
	public function __construct(
		public string $entityAlias,
		public string $entity,
		public string $name,
		public bool $isRelation,
	)
	{
		$this->path = $this->entityAlias . '.' . $this->name;
	}

}
