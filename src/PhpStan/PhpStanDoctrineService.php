<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use InvalidArgumentException;
use LogicException;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\UnionType;

/**
 * @api
 */
final readonly class PhpStanDoctrineService
{

	private PhpStanDoctrineEntityServiceFactory $entityServiceFactory;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		DescriptorRegistry $descriptorRegistry,
	)
	{
		$this->entityServiceFactory = new PhpStanDoctrineEntityServiceFactory(
			$objectMetadataResolver,
			$descriptorRegistry,
		);
	}

	public function tryGetSingleStringFromType(Type $type): ?string
	{
		foreach ($type->getConstantStrings() as $string) {
			return $string->getValue();
		}

		return null;
	}

	/**
	 * @param CriteriaItemType[] $criteriaCollection
	 */
	public function determineTypeByFieldCriteria(Type $type, string $fieldName, array $criteriaCollection): Type
	{
		foreach ($criteriaCollection as $criteria) {
			if ($criteria->fieldName !== $fieldName) {
				continue;
			}

			if ($criteria->operator === '=') {
				if ($type->isObject()->no() && $type->isArray()->no()) { // intersect '2020-01-01' with DateTime leads to *NEVER* type
					$typeToCombine = $this->getSimpleTypeForCombinator($criteria->valueType);

					return $typeToCombine === null ? $type : TypeCombinator::intersect($type, $typeToCombine);
				}

				if (($type->isNull()->maybe() || $type->isNull()->yes()) && $criteria->valueType->isNull()->no()) { // but we can still remove a null
					return TypeCombinator::removeNull($type);
				}

				return $type;
			}

			if ($criteria->operator === '!=') {
				$typeToCombine = $this->getSimpleTypeForCombinator($criteria->valueType);

				return $typeToCombine === null ? $type : TypeCombinator::remove($type, $typeToCombine);
			}
		}

		return $type;
	}

	private function getSimpleTypeForCombinator(Type $type): ?Type
	{
		if ($type->isScalar()->yes() || $type->isNull()->yes()) {
			return $type;
		}

		if ($type->isConstantArray()->yes()) {
			$types = [];

			foreach ($type->getConstantArrays() as $constantArray) {
				foreach ($constantArray->getValueTypes() as $valueType) {
					if (!$valueType->isScalar()->yes() && !$valueType->isNull()->yes()) {
						return null;
					}

					$types[] = $valueType;
				}
			}

			return new UnionType($types);
		}

		return null;
	}

	/**
	 * @return iterable<CriteriaItemType>
	 */
	public function getCriteriaFromType(Type $type): iterable
	{
		foreach ($this->iterateConstantArraysInType($type) as $keyType => $valueType) {
			if ($keyType->isInteger()->yes()) {
				continue; // Invalid key type for criteria
			}

			foreach ($keyType->getConstantStrings() as $constantString) {
				$fieldName = $constantString->getValue();
				$pos = strpos($fieldName, ' ');
				$operator = '=';

				if ($pos !== false) { // e.g. 'fieldName >='
					$operator = substr($fieldName, $pos + 1);
					$fieldName = substr($fieldName, 0, $pos);
				}

				yield new CriteriaItemType($fieldName, $operator, $valueType);
			}
		}
	}

	/**
	 * @return iterable<ConstantIntegerType|ConstantStringType, Type>
	 */
	private function iterateConstantArraysInType(Type $type): iterable
	{
		foreach ($type->getConstantArrays() as $constantArray) {
			$valueTypes = $constantArray->getValueTypes();

			foreach ($constantArray->getKeyTypes() as $i => $keyType) {
				$valueType = $valueTypes[$i] ?? null;
				if ($valueType === null) {
					throw new LogicException('Value type is null for integer key in constant array.');
				}

				yield $keyType => $valueType;
			}
		}
	}

	/**
	 * @return list<string>
	 */
	public function getFieldsFromOrderByType(Type $type): array
	{
		$fields = [];

		foreach ($this->iterateConstantArraysInType($type) as $keyType => $valueType) {
			foreach ($keyType->getConstantStrings() as $constantString) {
				$fields[] =$constantString->getValue();
			}
		}

		return $fields;
	}

	/**
	 * @return list<array{ string, string }> field, alias
	 */
	public function getFieldsFromSelectArrayType(Type $type): array
	{
		$select = [];

		foreach ($this->iterateConstantArraysInType($type) as $keyType => $valueType) {
			if ($keyType->isInteger()->yes()) {
				foreach ($valueType->getConstantStrings() as $constantString) {
					$select[] = [$constantString->getValue(), $constantString->getValue()];
				}

				continue;
			}

			foreach ($keyType->getConstantStrings() as $constantString) {
				$fieldName = $constantString->getValue();

				foreach ($valueType->getConstantStrings() as $valueConstantString) {
					$select[] = [$fieldName, $valueConstantString->getValue()];
				}
			}
		}

		return $select;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entityClassName
	 * @return PhpStanDoctrineEntityService<T>
	 */
	public function getEntityServiceFor(string $entityClassName): PhpStanDoctrineEntityService
	{
		return $this->entityServiceFactory->create($entityClassName);
	}

}
