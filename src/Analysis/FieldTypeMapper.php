<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Metadata\FieldType;

/**
 * Maps the Doctrine types of the fields to the types of a static analyser, the PHPStan extension uses
 * the descriptors of phpstan-doctrine.
 *
 * @template TType of object
 */
interface FieldTypeMapper
{

	/**
	 * @param bool $databaseValue Whether the value is returned as the driver fetches it (scalar queries, IDENTITY() of an association),
	 *                            otherwise it is converted by the Doctrine type (and the enum type) to its PHP value
	 * @return TType
	 */
	public function createType(FieldType $fieldType, bool $databaseValue): object;

	/**
	 * The type of the values the Doctrine type of the field converts to a database value. An object compared with the field
	 * is converted by it, see CriteriaParameterFactory, so an object of another type throws.
	 *
	 * @return TType|null Null when the type is not known, or when the Doctrine type refuses no object: it passes an object
	 *                    it does not know through and the driver binds it, e.g. a Stringable compared with a string column
	 */
	public function getTypeConvertedToDatabaseValue(FieldType $fieldType): ?object;

	/**
	 * Whether a row matched by "field = 'value'" returns exactly 'value', so the type can be narrowed to the string.
	 * It is not the case for a case-insensitive collation, or for columns the database converts, e.g. dates or GUIDs.
	 */
	public function comparesStringsExactly(FieldType $fieldType): bool;

}
