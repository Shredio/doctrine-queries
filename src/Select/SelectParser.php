<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Select;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use InvalidArgumentException;

final readonly class SelectParser
{

	public function __construct(
		private bool $requireAliases = false,
		private bool $requireRelations = false,
	)
	{
	}

	public function withRequireAliases(bool $requireAliases): self
	{
		return new self($requireAliases, $this->requireRelations);
	}

	public function withRequireRelations(bool $requireRelations): self
	{
		return new self($this->requireAliases, $requireRelations);
	}

	/**
	 * @param ClassMetadata<object> $metadata
	 * @param string[] $fields
	 * @return list<string>
	 */
	public function getFromSelect(ClassMetadata $metadata, array $fields, string $entityAlias): array
	{
		$return = [];
		$unique = [];

		foreach ($fields as $field => $alias) {
			if (is_int($field)) {
				$field = $alias;
				$useAlias = $this->requireAliases;
			} else {
				$useAlias = true;
			}

			$assoc = $metadata->hasAssociation($alias);

			if (isset($unique[$alias])) {
				throw new InvalidArgumentException(sprintf('Column "%s" is already selected. Please use unique column names.', $alias));
			}

			$unique[$alias] = true;

			if ($assoc) {
				$return[] = sprintf('IDENTITY(%s.%s) AS %s', $entityAlias, $field, $alias);
			} else if ($useAlias) {
				$return[] = sprintf('%s.%s AS %s', $entityAlias, $field, $alias);
			} else {
				$return[] = sprintf('%s.%s', $entityAlias, $field);
			}
		}

		return $return;
	}

	/**
	 * @param ClassMetadata<object> $metadata
	 * @return list<string>
	 */
	public function getForAll(ClassMetadata $metadata, string $entityAlias): array
	{
		if (!$this->requireAliases && !$this->requireRelations) {
			return [$entityAlias];
		}

		$return = [];

		foreach ($metadata->getFieldNames() as $field) {
			if ($this->requireAliases) {
				$return[] = sprintf('%s.%s AS %s', $entityAlias, $field, $field);
			} else {
				$return[] = sprintf('%s.%s', $entityAlias, $field);
			}
		}

		if ($this->requireRelations) {
			foreach ($metadata->getAssociationMappings() as $mapping) {
				if (!$mapping instanceof ManyToOneAssociationMapping) {
					continue;
				}

				$return[] = sprintf('IDENTITY(%s.%s) AS %s', $entityAlias, $mapping->fieldName, $mapping->fieldName);
			}
		}

		return $return;
	}
	
}
