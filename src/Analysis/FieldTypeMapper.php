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
	 * Whether a row matched by "field = 'value'" returns exactly 'value', so the type can be narrowed to the string.
	 * It is not the case for a case-insensitive collation, or for columns the database converts, e.g. dates or GUIDs.
	 */
	public function comparesStringsExactly(FieldType $fieldType): bool;

}
