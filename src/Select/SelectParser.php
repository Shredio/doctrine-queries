<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Select;

use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\InvalidSelectAliasException;
use Shredio\DoctrineQueries\Exception\NonUniqueSelectAliasException;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;

final readonly class SelectParser
{

	/**
	 * Identifiers of DQL, the aliases are written to the DQL as they are.
	 */
	private const string AliasPattern = '/^[A-Za-z_][A-Za-z0-9_]*$/D';

	/**
	 * @param string[] $fields
	 * @param list<NonUniqueSelectAliasException|InvalidSelectAliasException|FieldNotExistsException|InvalidAssociationPathException>|null $errors Collects the errors instead of throwing them when an array is passed
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
					if (preg_match(self::AliasPattern, $fieldToSelect->alias) !== 1) {
						$error = new InvalidSelectAliasException($fieldToSelect->alias, $fieldToSelect->metadata->field->selector);
						if ($errors === null) {
							throw $error;
						}

						$errors[] = $error;
						continue;
					}

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
			} catch (FieldNotExistsException|InvalidAssociationPathException $exception) {
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
			if ($queryType === QueryType::SubQuery) {
				$path = $metadata->getPathForField($fieldToSelect->metadata->field);
				$return[] = $fieldToSelect->metadata->isAssociation ? sprintf('IDENTITY(%s)', $path) : $path;
			} else if ($fieldToSelect->metadata->isAssociation) {
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

		if ($queryType === QueryType::Object || $queryType === QueryType::SubQuery) {
			return [$rootAlias];
		}

		// The fields are listed for arrays too: hydrating the entity itself would add the join columns of associations
		// that are part of the identifier (e.g. "prompt_id"), which are not fields and are missing in every other selection.
		$return = [];

		foreach ($metadata->getFieldNames() as $field) {
			$return[] = $queryType->isAliasesRequired()
				? sprintf('%s.%s AS %s', $rootAlias, $field, $field)
				: sprintf('%s.%s', $rootAlias, $field);
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
