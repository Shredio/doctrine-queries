<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Aggregation\SumKind;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Exception\UnsupportedFieldTypeException;
use Shredio\DoctrineQueries\Metadata\FieldMetadata;
use Shredio\DoctrineQueries\Metadata\FieldType;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * Resolves the types of the selected fields and narrows them by the criteria every row matches.
 *
 * @template TType of object
 * @internal
 */
final readonly class FieldTypeResolver
{

	/**
	 * @param TypeSystem<TType> $types
	 * @param FieldTypeMapper<TType> $fieldTypeMapper
	 */
	public function __construct(
		private TypeSystem $types,
		private FieldTypeMapper $fieldTypeMapper,
	)
	{
	}

	/**
	 * @param list<list<CriteriaItem<TType>>> $criteriaVariants See QueryArgumentReader::getCriteriaVariants()
	 * @param bool|null $isInnerJoin Whether the field is selected through an inner join, null for a field of the root entity
	 * @return TType
	 */
	public function resolve(FieldMetadata $fieldMetadata, QueryType $queryType, array $criteriaVariants, ?bool $isInnerJoin): object
	{
		try {
			$fieldType = $fieldMetadata->getFieldType($isInnerJoin);
		} catch (UnsupportedFieldTypeException) {
			return $this->types->mixed(); // e.g. a to-many association, reported by the validation
		}

		// Doctrine converts selected fields to PHP values except in scalar queries, but never IDENTITY() of an association.
		$isDatabaseValue = $queryType === QueryType::Scalar || $fieldMetadata->isAssociation;
		$type = $this->createType($fieldType, $isDatabaseValue);

		// The query runs with one of the variants, so a row has the type narrowed by any of them.
		$narrowedTypes = [];
		foreach ($criteriaVariants as $criteria) {
			$narrowedTypes[] = $this->narrowByCriteria(
				$type,
				$fieldMetadata->field->selector, // the selector, the name alone does not tell "id" from "author.id"
				$criteria,
				$this->fieldTypeMapper->comparesStringsExactly($fieldType),
			);
		}

		return $narrowedTypes === [] ? $type : $this->types->union(...$narrowedTypes);
	}

	/**
	 * The type of a value of the field, as FieldTypeMapper::createType() maps it. The integers of an integer column mapped
	 * as unsigned are not negative, the other values it can be returned in (a numeric string, an enum) are left as they are.
	 *
	 * @return TType
	 */
	public function createType(FieldType $fieldType, bool $databaseValue): object
	{
		$type = $this->fieldTypeMapper->createType($fieldType, $databaseValue);

		if (!$fieldType->unsigned || $this->types->isMixed($type) || SumKind::fromDoctrineType($fieldType->type) !== SumKind::Integer) {
			return $type;
		}

		return $this->types->remove($type, $this->types->intRange(null, -1));
	}

	/**
	 * Narrows the type by every criteria of the field, a row matches them all.
	 *
	 * @param TType $type
	 * @param list<CriteriaItem<TType>> $criteria Criteria every row matches
	 * @param bool $stringValuesAreExact Whether matched rows contain the string criteria values exactly, see FieldTypeMapper::comparesStringsExactly()
	 * @return TType
	 */
	public function narrowByCriteria(object $type, string $fieldSelector, array $criteria, bool $stringValuesAreExact = true): object
	{
		foreach ($criteria as $item) {
			if ($item->field->selector !== $fieldSelector) {
				continue;
			}

			$narrowedType = $this->narrowByCriteriaItem($type, $item, $stringValuesAreExact);

			// Criteria that exclude each other (id = 1 and id != 1) match no row, the type narrowed so far is kept.
			if (!$this->types->isNever($narrowedType)) {
				$type = $narrowedType;
			}
		}

		return $type;
	}

	/**
	 * @param TType $type
	 * @param CriteriaItem<TType> $item
	 * @return TType
	 */
	private function narrowByCriteriaItem(object $type, CriteriaItem $item, bool $stringValuesAreExact): object
	{
		if ($item->operator === '=') {
			$valueTypes = $this->getComparedValueTypes($item->valueType);
			$narrowedType = $valueTypes === null ? null : $this->narrowToValues($type, $valueTypes, $stringValuesAreExact);

			if ($narrowedType !== null) {
				return $narrowedType;
			}

			// if we can be null, but criteria does not allow null, we can remove it
			return $this->canCompareWithNull($item->valueType) ? $type : $this->types->removeNull($type);
		}

		if ($item->operator === '!=') {
			foreach ($this->getExcludedValueTypes($item->valueType) as $excludedValueType) {
				$type = $this->types->remove($type, $excludedValueType);
			}

			return $type;
		}

		if (CriteriaParser::normalizeOperator($item->operator) !== null) {
			// <, <=, >, >=, LIKE and NOT LIKE are not true for NULL, and they cannot be compared with null or a list
			return $this->types->removeNull($type);
		}

		return $type; // an unsupported operator, reported by the validation
	}

	/**
	 * Whether the compared value can be null or a list with a null, both are compared by IS NULL, so rows with NULL match.
	 *
	 * @param TType $valueType
	 */
	private function canCompareWithNull(object $valueType): bool
	{
		if ($this->types->canBeNull($valueType)) {
			return true; // also a value whose type is not known, e.g. mixed
		}

		return $this->types->canIterateNull($valueType); // every iterable is compared as a list, not only an array
	}

	/**
	 * The types of the values no row matched by "field != value" contains.
	 *
	 * Only a value that is known exactly is excluded: a row can contain 2 when it is compared with 1|2 and the value is 1,
	 * and it can contain any other integer than the compared one when it is compared with int.
	 *
	 * @param TType $valueType
	 * @return list<TType>
	 */
	private function getExcludedValueTypes(object $valueType): array
	{
		$members = $this->types->getUnionMembers($valueType);
		if (count($members) === 1) {
			return $this->getValueTypesExcludedBy($valueType);
		}

		// One of the values is compared, e.g. 1|null or 1|array{2, 3}. NULL is excluded when each of them excludes it.
		foreach ($members as $member) {
			if ($this->getValueTypesExcludedBy($member) === []) {
				return [];
			}
		}

		return [$this->types->null()];
	}

	/**
	 * @param TType $valueType A type that is not a union
	 * @return list<TType> With the type of NULL whenever a value is excluded: "field != 1", NOT IN and IS NOT NULL are not true for NULL
	 */
	private function getValueTypesExcludedBy(object $valueType): array
	{
		if ($this->types->isNull($valueType)) {
			return [$valueType]; // IS NOT NULL
		}

		if ($this->isComparedAsValue($valueType)) {
			return [$this->types->null(), ...$this->getExactValueTypes($valueType)];
		}

		$constantArrays = $this->types->isConstantArray($valueType) ? $this->types->getConstantArrays($valueType) : [];
		if (count($constantArrays) !== 1) {
			return []; // not a list, or the values of the list are not known
		}

		$excludedValueTypes = [];
		foreach ($constantArrays[0]->items as $item) {
			if ($item->optional) {
				continue; // the value may not be in the list
			}

			// The list is not empty: NOT IN matches no NULL, and a list of the only value NULL is compared by IS NOT NULL.
			$excludedValueTypes[] = $this->types->null();

			if ($this->isComparedAsValue($item->valueType)) {
				array_push($excludedValueTypes, ...$this->getExactValueTypes($item->valueType));
			}
		}

		return $excludedValueTypes;
	}

	/**
	 * The value when it is known exactly: a single literal (e.g. 1, 'a', true), or a case of an enum together with
	 * the value it is compared by. Nothing otherwise (e.g. int, 1|2, bool).
	 *
	 * @param TType $type
	 * @return list<TType>
	 */
	private function getExactValueTypes(object $type): array
	{
		if ($this->types->isEnumCase($type)) {
			$enumValueType = $this->types->getEnumCaseValueType($type);

			// the case for a column converted to the enum, its value for a column returned as it is stored
			return $enumValueType === null ? [$type] : [$type, $enumValueType];
		}

		$constantScalarTypes = $this->types->getConstantScalarTypes($type);

		return count($constantScalarTypes) === 1 ? $constantScalarTypes : [];
	}

	/**
	 * Narrows the type to the values the criteria compares with. Returns null when a value can be returned
	 * in another form than it was compared in, e.g. the numeric string '1' for an integer column,
	 * false for a boolean column that the driver returns as 0, or 'A' for a case-insensitive column that contains 'a'.
	 *
	 * @param TType $type
	 * @param non-empty-list<TType> $valueTypes
	 * @return TType|null
	 */
	private function narrowToValues(object $type, array $valueTypes, bool $stringValuesAreExact): ?object
	{
		$narrowedTypes = [];
		$typeWithoutNull = $this->types->removeNull($type);

		foreach ($valueTypes as $valueType) {
			$members = $this->types->getConstantScalarTypes($valueType);

			foreach ($members !== [] ? $members : [$valueType] as $member) {
				if ($this->types->isNull($member)) {
					if ($this->types->canBeNull($type)) { // NULL never matches a non-nullable column
						$narrowedTypes[] = $member;
					}

					continue;
				}

				$returnedType = $this->getReturnedType($typeWithoutNull, $member, $stringValuesAreExact);
				if ($returnedType === null) {
					return null;
				}

				$narrowedType = $this->types->intersect($type, $returnedType);
				if (!$this->types->isNever($narrowedType)) { // otherwise no row contains the value, e.g. it is excluded by another criteria
					$narrowedTypes[] = $narrowedType;
				}
			}
		}

		return $narrowedTypes === [] ? null : $this->types->union(...$narrowedTypes);
	}

	/**
	 * The type a matched value is returned in, null when it is not known. A value is returned in the form it was
	 * compared in only when both are of the same kind, a case of an enum is returned as it is by a column converted
	 * to the enum, and as its value by a column returned as it is stored.
	 *
	 * @param TType $type The type of the column without null
	 * @param TType $value
	 * @return TType|null
	 */
	private function getReturnedType(object $type, object $value, bool $stringValuesAreExact): ?object
	{
		if ($this->types->isEnumCase($value)) {
			if ($this->types->isMixed($type)) {
				return null;
			}

			if (!$this->types->isNever($this->types->intersect($type, $value))) {
				return $value;
			}

			$value = $this->types->getEnumCaseValueType($value);
			if ($value === null) {
				return null;
			}
		}

		$valueKind = $this->types->getScalarKind($value);
		if ($valueKind === null || $valueKind !== $this->types->getScalarKind($type)) {
			return null;
		}

		return $valueKind !== ScalarKind::String || $stringValuesAreExact ? $value : null;
	}

	/**
	 * The types of the values a field is compared with: a single value or the values of a list (IN, NOT IN),
	 * of each member when the value is one of several (e.g. 1|null or 1|array{2, 3}).
	 * Null when a value is not a scalar, null or a case of an enum.
	 *
	 * @param TType $type
	 * @return non-empty-list<TType>|null
	 */
	private function getComparedValueTypes(object $type): ?array
	{
		$types = [];

		foreach ($this->types->getUnionMembers($type) as $member) {
			if ($this->isComparedAsValue($member) || $this->types->isNull($member)) {
				$types[] = $member;

				continue;
			}

			$constantArrays = $this->types->isConstantArray($member) ? $this->types->getConstantArrays($member) : [];
			if ($constantArrays === []) {
				return null;
			}

			foreach ($constantArrays as $constantArray) {
				foreach ($constantArray->items as $item) {
					foreach ($this->types->getUnionMembers($item->valueType) as $itemMember) {
						if (!$this->isComparedAsValue($itemMember) && !$this->types->isNull($itemMember)) {
							return null;
						}

						$types[] = $itemMember;
					}
				}
			}
		}

		return $types === [] ? null : $types;
	}

	/**
	 * Whether a value of the type is compared by "=" or "!=" as a single value that is not null.
	 *
	 * @param TType $type
	 */
	private function isComparedAsValue(object $type): bool
	{
		return $this->types->isScalar($type) || $this->types->isEnumCase($type);
	}

}
