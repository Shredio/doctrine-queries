<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use Doctrine\ORM\Mapping\FieldMapping;
use LogicException;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
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
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\Metadata\FieldType;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * @api
 */
final readonly class PhpStanDoctrineService
{

	private PhpstanClassMetadataFactory $classMetadataFactory;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		private DescriptorRegistry $descriptorRegistry,
	)
	{
		$this->classMetadataFactory = new PhpstanClassMetadataFactory($objectMetadataResolver);
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
			if ($criteria->field->selector !== $fieldName) {
				continue;
			}

			$criteriaType = $criteria->valueType;

			if ($criteria->operator === '=') {
				if ($type->isObject()->no() && $type->isArray()->no()) { // intersect '2020-01-01' with DateTime leads to *NEVER* type
					$typeToCombine = $this->getSimpleTypeForCombinator($criteriaType);

					if ($typeToCombine === null && $this->canBeNull($type) && !$this->containsNull($criteriaType)) {
						return TypeCombinator::removeNull($type); // if we can be null, but criteria does not allow null, we can remove it
					}

					return $typeToCombine === null ? $type : TypeCombinator::intersect($type, $typeToCombine);
				}

				if ($this->canBeNull($type) && !$this->containsNull($criteriaType)) {
					return TypeCombinator::removeNull($type); // if we can be null, but criteria does not allow null, we can remove it
				}

				return $type;
			}

			if ($criteria->operator === '!=') {
				$typeToCombine = $this->getSimpleTypeForCombinator($criteriaType);

				return $typeToCombine === null ? $type : TypeCombinator::remove($type, $typeToCombine);
			}
		}

		return $type;
	}

	private function containsNull(Type $type): bool
	{
		if ($type->isArray()->yes()) {
			foreach ($type->getArrays() as $arrayType) {
				if (TypeCombinator::containsNull($arrayType->getItemType())) {
					return true;
				}
			}

			return false;
		}

		return TypeCombinator::containsNull($type);
	}

	private function canBeNull(Type $type): bool
	{
		return $type->isNull()->yes() || $type->isNull()->maybe();
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
			foreach ($keyType->getConstantStrings() as $constantString) {
				[$field, $operator] = CriteriaParser::parseSingleField($constantString->getValue());

				yield new CriteriaItemType($field, $operator, $valueType);
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
	 * @return list<Field>
	 */
	public function getFieldsFromOrderByType(Type $type): array
	{
		$fields = [];

		foreach ($this->iterateConstantArraysInType($type) as $keyType => $valueType) {
			foreach ($keyType->getConstantStrings() as $constantString) {
				$fields[] = new Field($constantString->getValue());
			}
		}

		return $fields;
	}

	/**
	 * @return list<array{ Field, string }> field, alias
	 */
	public function getFieldsFromSelectArrayType(Type $type): array
	{
		$select = [];

		foreach ($this->iterateConstantArraysInType($type) as $keyType => $valueType) {
			if ($keyType->isInteger()->yes()) {
				foreach ($valueType->getConstantStrings() as $constantString) {
					$select[] = [new Field($constantString->getValue()), $constantString->getValue()];
				}

				continue;
			}

			foreach ($keyType->getConstantStrings() as $constantString) {
				$fieldName = $constantString->getValue();

				foreach ($valueType->getConstantStrings() as $valueConstantString) {
					$select[] = [new Field($fieldName), $valueConstantString->getValue()];
				}
			}
		}

		return $select;
	}

	/**
	 * @return array<string|int, string> field, alias
	 */
	public function getSelectFromType(Type $type): array
	{
		$select = [];

		foreach ($this->iterateConstantArraysInType($type) as $keyType => $valueType) {
			if ($keyType->isInteger()->yes()) {
				foreach ($valueType->getConstantStrings() as $constantString) {
					$select[] = $constantString->getValue();
				}

				continue;
			}

			foreach ($keyType->getConstantStrings() as $constantString) {
				$fieldName = $constantString->getValue();

				foreach ($valueType->getConstantStrings() as $valueConstantString) {
					$select[$fieldName] = $valueConstantString->getValue();
				}
			}
		}

		return $select;
	}

	public function createTypeForFieldMapping(FieldType $fieldType, bool $returnScalarType = false): Type
	{
		$nullable = $fieldType->nullable;

		return $this->resolveDoctrineType(
			$fieldType->type,
			$fieldType->enumType,
			$nullable,
			$returnScalarType,
		);
	}

	/**
	 * @param class-string $entityClassName
	 * @throws NoClassMetadataException
	 */
	public function getQueryMetadataFor(string $entityClassName, QueryType $queryType): QueryMetadata
	{
		return new QueryMetadata(
			$this->classMetadataFactory,
			$this->classMetadataFactory->getMetadataFor($entityClassName),
			$queryType,
		);
	}

	/**
	 * @see https://github.com/phpstan/phpstan-doctrine/blob/2.0.x/src/Type/Doctrine/Query/QueryResultTypeWalker.php#L2026
	 */
	private function resolveDoctrineType(
		string $typeName,
		?string $enumType = null,
		bool $nullable = false,
		bool $returnScalarType = false,
	): Type
	{
		try {
			$descriptor = $this->descriptorRegistry->get($typeName); // @phpstan-ignore phpstanApi.method

			if ($returnScalarType) {
				$type = $descriptor->getDatabaseInternalType();
			} else {
				$type = $descriptor->getWritableToPropertyType();
			}

			if (!$returnScalarType && $enumType !== null) {
				if ($type->isArray()->no()) {
					$type = new ObjectType($enumType);
				} else {
					$type = TypeCombinator::intersect(new ArrayType(
						$type->getIterableKeyType(),
						new ObjectType($enumType),
					), ...TypeUtils::getAccessoryTypes($type));
				}
			}
			if ($type instanceof NeverType) {
				$type = new MixedType();
			}
		} catch (DescriptorNotRegisteredException) {
			if (!$returnScalarType && $enumType !== null) {
				$type = new ObjectType($enumType);
			} else {
				$type = new MixedType();
			}
		}

		if ($nullable) {
			$type = TypeCombinator::addNull($type);
		}

		return $type;
	}

	/**
	 * @return array<array-key, scalar|null>
	 */
	public function getArrayFromConstantArray(Type $type): array
	{
		$values = [];

		foreach ($this->iterateConstantArraysInType($type) as $keyType => $valueType) {
			foreach ($keyType->getConstantScalarValues() as $key) {
				foreach ($valueType->getConstantScalarValues() as $value) {
					$values[$key] = $value;
				}
			}
		}

		return $values;
	}

}
