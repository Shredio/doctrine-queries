<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type as DbalType;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PDO;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDriverAwareDescriptor;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\Metadata\FieldType;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * @api
 */
final class PhpStanDoctrineService
{

	/**
	 * Drivers whose "=" compares strings exactly, MySQL compares case-insensitively with its default collations.
	 * The names are the ones DriverDetector returns.
	 */
	private const array ExactStringComparisonDrivers = ['pdo_sqlite', 'sqlite3', 'pdo_pgsql', 'pgsql'];

	private const array PdoDrivers = ['pdo_mysql', 'pdo_pgsql', 'pdo_sqlite'];

	private readonly PhpstanClassMetadataFactory $classMetadataFactory;

	/** The connection of the objectManagerLoader tells which driver fetches the scalar results. */
	private ?Connection $connection = null;

	private bool $connectionResolved = false;

	public function __construct(
		private readonly ObjectMetadataResolver $objectMetadataResolver,
		private readonly DescriptorRegistry $descriptorRegistry,
		// DriverDetector is not covered by the BC promise of phpstan-doctrine, but its driver-aware descriptors use it too,
		// so the detected driver always agrees with the types they return.
		private readonly DriverDetector $driverDetector = new DriverDetector(), // @phpstan-ignore phpstanApi.constructor
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
	 * Whether a row matched by "field = 'value'" returns exactly 'value', so the type can be narrowed to the string.
	 * It is not the case for a case-insensitive collation, or for columns the database converts, e.g. dates or GUIDs.
	 */
	public function comparesStringsExactly(FieldType $fieldType): bool
	{
		$connection = $this->getConnection();
		if ($connection === null || !in_array($this->driverDetector->detect($connection), self::ExactStringComparisonDrivers, true)) { // @phpstan-ignore phpstanApi.method
			return false;
		}

		if (!DbalType::hasType($fieldType->type)) {
			return false;
		}

		$dbalType = DbalType::getType($fieldType->type);

		return ($dbalType instanceof StringType || $dbalType instanceof TextType) && !$dbalType instanceof GuidType;
	}

	/**
	 * @param CriteriaItemType[] $criteriaCollection
	 * @param bool $stringValuesAreExact Whether matched rows contain the string criteria values exactly, see comparesStringsExactly()
	 */
	public function determineTypeByFieldCriteria(Type $type, string $fieldName, array $criteriaCollection, bool $stringValuesAreExact = true): Type
	{
		foreach ($criteriaCollection as $criteria) {
			if ($criteria->field->selector !== $fieldName) {
				continue;
			}

			$criteriaType = $criteria->valueType;

			if ($criteria->operator === '=') {
				if ($type->isObject()->no() && $type->isArray()->no()) { // intersect '2020-01-01' with DateTime leads to *NEVER* type
					$valueTypes = $this->getSimpleTypesForCombinator($criteriaType);
					$narrowedType = $valueTypes === null ? null : $this->narrowToValues($type, $valueTypes, $stringValuesAreExact);

					if ($narrowedType !== null) {
						return $narrowedType;
					}
				}

				if ($this->canBeNull($type) && !$this->containsNull($criteriaType)) {
					return TypeCombinator::removeNull($type); // if we can be null, but criteria does not allow null, we can remove it
				}

				return $type;
			}

			if ($criteria->operator === '!=') {
				$valueTypes = $this->getSimpleTypesForCombinator($criteriaType);

				return $valueTypes === null ? $type : TypeCombinator::remove($type, TypeCombinator::union(...$valueTypes));
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

	/**
	 * Narrows the type to the values the criteria compares with. Returns null when a value can be returned
	 * in another form than it was compared in, e.g. the numeric string '1' for an integer column,
	 * false for a boolean column that the driver returns as 0, or 'A' for a case-insensitive column that contains 'a'.
	 *
	 * @param list<Type> $valueTypes
	 */
	private function narrowToValues(Type $type, array $valueTypes, bool $stringValuesAreExact): ?Type
	{
		$narrowedTypes = [];
		$typeWithoutNull = TypeCombinator::removeNull($type);

		foreach ($valueTypes as $valueType) {
			$members = $valueType->getConstantScalarTypes();

			foreach ($members !== [] ? $members : [$valueType] as $member) {
				if (!$member->isNull()->yes() && !$this->isReturnedAsCompared($typeWithoutNull, $member, $stringValuesAreExact)) {
					return null;
				}

				$narrowedType = TypeCombinator::intersect($type, $member);

				if ($narrowedType instanceof NeverType) {
					if ($member->isNull()->yes()) {
						continue; // NULL never matches a non-nullable column
					}

					return null;
				}

				$narrowedTypes[] = $narrowedType;
			}
		}

		return $narrowedTypes === [] ? null : TypeCombinator::union(...$narrowedTypes);
	}

	/**
	 * A matched value is returned in the form it was compared in only when both are of the same kind.
	 */
	private function isReturnedAsCompared(Type $type, Type $value, bool $stringValuesAreExact): bool
	{
		return match (true) {
			$value->isInteger()->yes() => $type->isInteger()->yes(),
			$value->isFloat()->yes() => $type->isFloat()->yes(),
			$value->isBoolean()->yes() => $type->isBoolean()->yes(),
			$value->isString()->yes() => $stringValuesAreExact && $type->isString()->yes(),
			default => false,
		};
	}

	/**
	 * @return list<Type>|null
	 */
	private function getSimpleTypesForCombinator(Type $type): ?array
	{
		if ($type->isScalar()->yes() || $type->isNull()->yes()) {
			return [$type];
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

			return $types;
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

				// Unsupported operators are kept as they are, DoctrineQueriesRule reports them.
				yield new CriteriaItemType($field, CriteriaParser::normalizeOperator($operator) ?? $operator, $valueType);
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
	 * @return list<Field>
	 */
	public function getFieldsFromPreloadType(Type $type): array
	{
		$fields = [];

		foreach ($this->iterateConstantArraysInType($type) as $valueType) {
			foreach ($valueType->getConstantStrings() as $constantString) {
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
				$type = $this->resolveDatabaseInternalType($descriptor);
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
	 * Scalar results are not converted by Doctrine, so their type is the one the driver fetches.
	 *
	 * @see https://github.com/janedbal/php-database-drivers-fetch-test
	 */
	private function resolveDatabaseInternalType(DoctrineTypeDescriptor $descriptor): Type
	{
		$connection = $this->getConnection();
		if ($connection === null) {
			return $descriptor->getDatabaseInternalType();
		}

		$type = $descriptor instanceof DoctrineTypeDriverAwareDescriptor
			? $descriptor->getDatabaseInternalTypeForDriver($connection)
			: $descriptor->getDatabaseInternalType();

		if (!$this->isFetchStringified($connection)) {
			return $type;
		}

		$stringifiedType = TypeCombinator::removeNull($type)->toString();

		return TypeCombinator::containsNull($type) ? TypeCombinator::addNull($stringifiedType) : $stringifiedType;
	}

	/**
	 * PDO returns every value as a string with PDO::ATTR_STRINGIFY_FETCHES, other drivers fetch native types on PHP 8.1+.
	 */
	private function isFetchStringified(Connection $connection): bool
	{
		if (!in_array($this->driverDetector->detect($connection), self::PdoDrivers, true)) { // @phpstan-ignore phpstanApi.method
			return false;
		}

		return (bool) ($this->driverDetector->detectDriverOptions($connection)[PDO::ATTR_STRINGIFY_FETCHES] ?? false); // @phpstan-ignore phpstanApi.method
	}

	private function getConnection(): ?Connection
	{
		if (!$this->connectionResolved) {
			$this->connectionResolved = true;
			$objectManager = $this->objectMetadataResolver->getObjectManager();
			$this->connection = $objectManager instanceof EntityManagerInterface ? $objectManager->getConnection() : null;
		}

		return $this->connection;
	}

	/**
	 * @return array<array-key, scalar|null>
	 */
	public function getArrayFromConstantArray(Type $type): array
	{
		$values = [];

		foreach ($this->iterateConstantArraysInType($type) as $keyType => $valueType) {
			foreach ($valueType->getConstantScalarValues() as $value) {
				$values[$keyType->getValue()] = $value;
			}
		}

		return $values;
	}

}
