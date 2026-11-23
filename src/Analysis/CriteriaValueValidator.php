<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\UnsupportedFieldTypeException;
use Shredio\DoctrineQueries\Parameter\DoctrineParameterAware;
use Shredio\DoctrineQueries\Query\SubQuery;

/**
 * Reports the objects in the criteria values that the type of the compared column does not convert.
 *
 * An object compared with a field is converted by the Doctrine type of its column, see CriteriaParameterFactory, and
 * a type that does not know the object throws when the query runs. The rules of the binding are followed here, only
 * a value that certainly throws is reported.
 *
 * @template TType of object
 * @internal
 */
final readonly class CriteriaValueValidator
{

	/**
	 * The objects passed alone that are not converted by the type of the column: a sub-query, a value creating its own
	 * parameter and the values Doctrine binds by their own type. A date is converted only by a column of a date type,
	 * which takes every date.
	 */
	private const array SingleValuesNotConverted = [
		SubQuery::class,
		QueryBuilder::class,
		Query::class,
		DoctrineParameterAware::class,
		BackedEnum::class,
		DateTimeInterface::class,
		DateInterval::class,
	];

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
	 * @param CriteriaItem<TType> $item
	 */
	public function validate(ValidatedCall $call, CriteriaItem $item): ?AnalysisError
	{
		try {
			$fieldMetadata = $call->queryMetadata->getFieldMetadata($item->field);
			$fieldType = $fieldMetadata->getFieldType();
		} catch (FieldNotExistsException|InvalidAssociationPathException) {
			return null; // reported by the validation of the field
		} catch (UnsupportedFieldTypeException) {
			return null; // no single column, the value is bound as it is
		}

		$convertedType = $this->fieldTypeMapper->getTypeConvertedToDatabaseValue($fieldType);
		if ($convertedType === null) {
			return null;
		}

		// An instance of the entity of an association is replaced by its identifier before the conversion.
		$entityType = $fieldMetadata->isAssociation
			? $this->types->objectType($fieldMetadata->getAssociationMapping()->targetEntity)
			: null;

		$refusedType = $this->findRefusedType($item->valueType, $convertedType, $entityType);
		if ($refusedType === null) {
			return null;
		}

		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - criteria `%s` of entity %s is compared with %s, the type `%s` of its column converts only %s.',
				$call->className,
				$call->methodName,
				$item->field->selector,
				$call->entityClassName,
				$this->types->describe($refusedType),
				$fieldType->type,
				$this->types->describe($convertedType),
			),
			'criteriaValueType',
		);
	}

	/**
	 * The type of the objects the column type refuses, null when the value can be one that is bound: a value that is not
	 * an object, an object the type converts or one whose type is not known exactly.
	 *
	 * @param TType $valueType
	 * @param TType $convertedType
	 * @param TType|null $entityType
	 * @return TType|null
	 */
	private function findRefusedType(object $valueType, object $convertedType, ?object $entityType): ?object
	{
		$refusedTypes = [];

		foreach ($this->types->getUnionMembers($valueType) as $member) {
			if ($this->types->isNull($member)) {
				continue; // compared by IS NULL
			}

			if ($this->types->isIterable($member)) {
				$refusedType = $this->findRefusedListItemType($member, $convertedType, $entityType);
			} else if ($this->types->isObject($member) && $this->isSingleValueRefused($member, $convertedType, $entityType)) {
				$refusedType = $member;
			} else {
				$refusedType = null;
			}

			if ($refusedType === null) {
				return null;
			}

			$refusedTypes[] = $refusedType;
		}

		return $refusedTypes === [] ? null : $this->types->union(...$refusedTypes);
	}

	/**
	 * @param TType $valueType An object that is not a union
	 * @param TType $convertedType
	 * @param TType|null $entityType
	 */
	private function isSingleValueRefused(object $valueType, object $convertedType, ?object $entityType): bool
	{
		foreach (self::SingleValuesNotConverted as $className) {
			if ($this->types->isSuperTypeOf($this->types->objectType($className), $valueType) === true) {
				return false;
			}
		}

		return $this->isObjectRefused($valueType, $convertedType, $entityType);
	}

	/**
	 * Every item of a list is converted, so one refused item is enough when the items are known. A list whose items
	 * are not known is refused when each of its items would be.
	 *
	 * @param TType $listType
	 * @param TType $convertedType
	 * @param TType|null $entityType
	 * @return TType|null
	 */
	private function findRefusedListItemType(object $listType, object $convertedType, ?object $entityType): ?object
	{
		if (!$this->types->isConstantArray($listType)) {
			return $this->findRefusedItemType($this->types->getIterableValueType($listType), $convertedType, $entityType);
		}

		foreach ($this->types->getConstantArrays($listType) as $constantArray) {
			foreach ($constantArray->items as $item) {
				$refusedType = $item->optional ? null : $this->findRefusedItemType($item->valueType, $convertedType, $entityType);
				if ($refusedType !== null) {
					return $refusedType;
				}
			}
		}

		return null;
	}

	/**
	 * @param TType $itemType
	 * @param TType $convertedType
	 * @param TType|null $entityType
	 * @return TType|null
	 */
	private function findRefusedItemType(object $itemType, object $convertedType, ?object $entityType): ?object
	{
		$refusedTypes = [];

		foreach ($this->types->getUnionMembers($itemType) as $member) {
			if ($this->types->isNull($member)) {
				continue; // compared by IS NULL
			}

			if (!$this->types->isObject($member) || !$this->isItemRefused($member, $convertedType, $entityType)) {
				return null;
			}

			$refusedTypes[] = $member;
		}

		return $refusedTypes === [] ? null : $this->types->union(...$refusedTypes);
	}

	/**
	 * In a list only enums are not converted by the type of the column. A DateTime is converted as DateTimeImmutable
	 * when the type refuses it.
	 *
	 * @param TType $itemType An object that is not a union
	 * @param TType $convertedType
	 * @param TType|null $entityType
	 */
	private function isItemRefused(object $itemType, object $convertedType, ?object $entityType): bool
	{
		if ($this->types->isSuperTypeOf($this->types->objectType(BackedEnum::class), $itemType) === true) {
			return false;
		}

		if (!$this->isObjectRefused($itemType, $convertedType, $entityType)) {
			return false;
		}

		if ($this->types->isSuperTypeOf($this->types->objectType(DateTimeInterface::class), $itemType) !== true) {
			return true;
		}

		return $this->types->isSuperTypeOf($convertedType, $this->types->objectType(DateTimeImmutable::class)) === false;
	}

	/**
	 * @param TType $objectType
	 * @param TType $convertedType
	 * @param TType|null $entityType
	 */
	private function isObjectRefused(object $objectType, object $convertedType, ?object $entityType): bool
	{
		if ($entityType !== null && $this->types->isSuperTypeOf($entityType, $objectType) !== false) {
			return false;
		}

		return $this->types->isSuperTypeOf($convertedType, $objectType) === false;
	}

}
