<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Select;

use InvalidArgumentException;
use Shredio\DoctrineQueries\Field\EntityMetadata;
use Shredio\DoctrineQueries\Field\FieldPath;
use Shredio\DoctrineQueries\Field\MappedFieldPath;
use Shredio\DoctrineQueries\Hydration\HydrationType;

final readonly class SelectParser
{


	/**
	 * @param EntityMetadata<object> $metadata
	 * @param string[] $fields
	 * @return list<string>
	 */
	public function getFromSelect(EntityMetadata $metadata, array $fields, HydrationType $hydrationType): array
	{
		$return = [];
		$unique = [];

		foreach ($fields as $field => $alias) {
			if (is_int($field)) {
				$field = $alias;
			}

			$field = $metadata->createField(FieldPath::createFromString($field));

			if (isset($unique[$alias])) {
				throw new InvalidArgumentException(sprintf('Column "%s" is already selected. Please use unique column names.', $alias));
			}

			$unique[$alias] = true;
			$return[] = $this->createSelect($field, $alias, $hydrationType);
		}

		return $return;
	}

	/**
	 * @param EntityMetadata<object> $metadata
	 * @return list<string>
	 */
	public function getForAll(EntityMetadata $metadata, HydrationType $hydrationType, bool $withRelations = false): array
	{
		if ($hydrationType === HydrationType::Object) {
			return [$metadata->alias];
		}

		if (!$withRelations && $hydrationType !== HydrationType::Scalar) {
			return [$metadata->alias];
		}

		$return = [];

		foreach ($metadata->getSelectableFields($withRelations) as $field) {
			$return[] = $this->createSelect($field, $field->name, $hydrationType);
		}

		return $return;
	}

	private function createSelect(MappedFieldPath $field, string $alias, HydrationType $hydrationType): string
	{
		if ($field->isRelation && $hydrationType !== HydrationType::Object) {
			return sprintf('IDENTITY(%s) AS %s', $field->path, $alias);
		}

		$requireAliases = $hydrationType === HydrationType::Scalar;
		if ($requireAliases || $alias !== $field->name) {
			return sprintf('%s AS %s', $field->path, $alias);
		}

		return $field->path;
	}

}
