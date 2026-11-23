<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use LogicException;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use Shredio\DoctrineQueries\Analysis\ConstantArray;
use Shredio\DoctrineQueries\Analysis\ConstantArrayItem;
use Shredio\DoctrineQueries\Analysis\ScalarKind;
use Shredio\DoctrineQueries\Analysis\TypeSystem;
use Shredio\DoctrineQueries\Criteria\AnyOf;

/**
 * @implements TypeSystem<Type>
 */
final readonly class PhpStanTypeSystem implements TypeSystem
{

	public function getConstantStrings(object $type): array
	{
		return array_map(static fn (ConstantStringType $string): string => $string->getValue(), $type->getConstantStrings());
	}

	public function getConstantScalarValues(object $type): array
	{
		return $type->getConstantScalarValues();
	}

	public function getConstantScalarTypes(object $type): array
	{
		return $type->getConstantScalarTypes();
	}

	public function getUnionMembers(object $type): array
	{
		return $type instanceof UnionType ? $type->getTypes() : [$type];
	}

	public function isEnumCase(object $type): bool
	{
		return !$type instanceof UnionType && $type->getEnumCaseObject() !== null;
	}

	public function getEnumCaseValueType(object $type): ?Type
	{
		return $type instanceof UnionType ? null : $type->getEnumCaseObject()?->getBackingValueType();
	}

	public function isConstantArray(object $type): bool
	{
		return $type->isConstantArray()->yes();
	}

	public function getConstantArrays(object $type): array
	{
		return array_map($this->createConstantArray(...), $type->getConstantArrays());
	}

	public function getIterableValueType(object $type): Type
	{
		return $type->getIterableValueType();
	}

	public function getAnyOfAlternatives(object $type): ?array
	{
		if (!(new ObjectType(AnyOf::class))->isSuperTypeOf($type)->yes()) {
			return null;
		}

		$alternativesType = $type->getTemplateType(AnyOf::class, 'TAlternatives');
		if (!$alternativesType->isConstantArray()->yes()) {
			return [$alternativesType->getIterableValueType()];
		}

		$alternativeTypes = [];
		foreach ($alternativesType->getConstantArrays() as $constantArray) {
			foreach ($constantArray->getValueTypes() as $alternativeType) {
				$alternativeTypes[] = $alternativeType;
			}
		}

		return $alternativeTypes;
	}

	public function getScalarKind(object $type): ?ScalarKind
	{
		return match (true) {
			$type->isInteger()->yes() => ScalarKind::Int,
			$type->isFloat()->yes() => ScalarKind::Float,
			$type->isBoolean()->yes() => ScalarKind::Bool,
			$type->isString()->yes() => ScalarKind::String,
			default => null,
		};
	}

	public function isScalar(object $type): bool
	{
		return $type->isScalar()->yes();
	}

	public function isNull(object $type): bool
	{
		return $type->isNull()->yes();
	}

	public function canBeNull(object $type): bool
	{
		return !$type->isNull()->no();
	}

	public function containsNull(object $type): bool
	{
		return TypeCombinator::containsNull($type);
	}

	public function isArray(object $type): bool
	{
		return $type->isArray()->yes();
	}

	public function canIterateNull(object $type): bool
	{
		foreach ($this->getUnionMembers($type) as $member) {
			$isIterated = $member->isObject()->yes() ? $member->isIterable()->yes() : !$member->isIterable()->no();

			if ($isIterated && !$member->getIterableValueType()->isNull()->no()) {
				return true;
			}
		}

		return false;
	}

	public function isNever(object $type): bool
	{
		return $type instanceof NeverType;
	}

	public function isMixed(object $type): bool
	{
		return $type instanceof MixedType;
	}

	public function isArrayKey(object $type): bool
	{
		return (new UnionType([new IntegerType(), new StringType()]))->isSuperTypeOf($type)->yes();
	}

	public function describe(object $type): string
	{
		return $type->describe(VerbosityLevel::typeOnly());
	}

	public function mixed(): Type
	{
		return new MixedType();
	}

	public function never(): Type
	{
		return new NeverType();
	}

	public function null(): Type
	{
		return new NullType();
	}

	public function arrayKey(): Type
	{
		return new UnionType([new StringType(), new IntegerType()]);
	}

	public function addNull(object $type): Type
	{
		return TypeCombinator::addNull($type);
	}

	public function removeNull(object $type): Type
	{
		return TypeCombinator::removeNull($type);
	}

	public function union(object $type, object ...$types): Type
	{
		return TypeCombinator::union($type, ...$types);
	}

	public function intersect(object $type, object $otherType): Type
	{
		return TypeCombinator::intersect($type, $otherType);
	}

	public function remove(object $type, object $typeToRemove): Type
	{
		return TypeCombinator::remove($type, $typeToRemove);
	}

	public function toArrayKey(object $type): Type
	{
		return $type->isObject()->no() ? $type->toArrayKey() : $type;
	}

	public function constantArray(array $valueTypes): Type
	{
		$builder = ConstantArrayTypeBuilder::createEmpty();
		foreach ($valueTypes as $key => $valueType) {
			$builder->setOffsetValueType(is_int($key) ? new ConstantIntegerType($key) : new ConstantStringType($key), $valueType);
		}

		return $builder->getArray();
	}

	public function genericObject(string $className, array $templateTypes): Type
	{
		return new GenericObjectType($className, $templateTypes);
	}

	/**
	 * @return ConstantArray<Type>
	 */
	private function createConstantArray(ConstantArrayType $constantArray): ConstantArray
	{
		$valueTypes = $constantArray->getValueTypes();
		$items = [];

		foreach ($constantArray->getKeyTypes() as $i => $keyType) {
			$items[] = new ConstantArrayItem(
				$keyType->getValue(),
				$valueTypes[$i] ?? throw new LogicException('Value type is missing for a key of a constant array.'),
				$constantArray->isOptionalKey($i),
			);
		}

		return new ConstantArray($items);
	}

}
