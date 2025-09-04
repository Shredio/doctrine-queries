<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Select;

use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\NonUniqueSelectAliasException;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;

final readonly class SelectParser
{

	/**
	 * @param string[] $fields
	 * @param list<NonUniqueSelectAliasException|FieldNotExistsException>|null $errors
	 * @return iterable<int, FieldToSelect>
	 */
	public static function getMetadataForSelection(QueryMetadata $metadata, array $fields, ?array &$errors = null): iterable
	{
		if ($fields === []) {
			// Select all fields
			yield from $metadata->getFieldsToSelect(new Field('*'));

			return;
		}

		$unique = [];

		foreach ($fields as $field => $alias) {
			if (is_int($field)) { // ['field']
				$field = new Field($alias);
				$alias = null;
			} else { // ['field' => 'alias']
				$field = new Field($field);
			}

			try {
				foreach ($metadata->getFieldsToSelect($field, $alias) as $fieldToSelect) {
					if (isset($unique[$fieldToSelect->alias])) {
						$error = new NonUniqueSelectAliasException($fieldToSelect->alias);
						if ($errors === null) {
							throw $error;
						}

						$errors[] = $error;
						continue;
					}

					$unique[$fieldToSelect->alias] = true;

					yield $fieldToSelect;
				}
			} catch (FieldNotExistsException $exception) {
				if ($errors !== null) {
					$errors[] = $exception;
				} else {
					throw $exception;
				}
			}
		}
	}

	/**
	 * @param string[] $fields
	 * @return list<string>
	 */
	public static function getForSelection(
		QueryMetadata $metadata,
		array $fields,
		QueryType $queryType,
	): array
	{
		if ($fields === []) {
			return self::getForAll($metadata, $queryType);
		}

		$return = [];

		foreach (self::getMetadataForSelection($metadata, $fields) as $fieldToSelect) {
			if ($fieldToSelect->metadata->isAssociation) {
				$return[] = sprintf('IDENTITY(%s) AS %s', $metadata->getPathForField($fieldToSelect->metadata->field), $fieldToSelect->alias);
			} else if (self::isAliasRequired($queryType, $fieldToSelect)) {
				$return[] = sprintf('%s AS %s', $metadata->getPathForField($fieldToSelect->metadata->field), $fieldToSelect->alias);
			} else {
				$return[] = $metadata->getPathForField($fieldToSelect->metadata->field);
			}
		}

		return $return;
	}

	/**
	 * @return list<string>
	 */
	private static function getForAll(QueryMetadata $metadata, QueryType $queryType): array
	{
		$rootAlias = $metadata->getRootAlias();

		if (!$queryType->isAliasesRequired()) {
			return [$rootAlias];
		}

		$return = [];

		foreach ($metadata->getFieldNames() as $field) {
			$return[] = sprintf('%s.%s AS %s', $rootAlias, $field, $field);
		}

		return $return;
	}

	private static function isAliasRequired(QueryType $queryType, FieldToSelect $fieldToSelect): bool
	{
		if ($queryType === QueryType::Scalar) {
			return true;
		}

		if ($fieldToSelect->metadata->isAssociation) {
			return true;
		}

		return $fieldToSelect->metadata->field->name !== $fieldToSelect->alias;
	}

}
