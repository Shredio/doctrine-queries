<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Parameter;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Doctrine\ORM\Query\Parameter;
use Shredio\DoctrineQueries\Exception\UnsupportedFieldTypeException;
use Shredio\DoctrineQueries\Metadata\FieldMetadata;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Select\Field;

/**
 * Creates the query parameter of a value compared with a field.
 *
 * Doctrine infers the type of a parameter from its PHP value alone. An object it does not know, the identifier of an
 * entity and the items of a list are bound as strings, so a value object of a custom DBAL type is bound by its
 * __toString() and a binary value as text. Such a parameter matches no row and raises no error. The type of the
 * compared column is known here, values Doctrine cannot bind by itself are converted and bound by it.
 *
 * @internal
 */
final readonly class CriteriaParameterFactory
{

	private function __construct()
	{
		// Prevent instantiation
	}

	/**
	 * @param Field $field Field or association the value is compared with, an association is compared by the identifier of its entity
	 * @param mixed $value Single value or an array of the values of a list, without NULL
	 */
	public static function create(
		EntityManagerInterface $entityManager,
		QueryMetadata $metadata,
		Field $field,
		string $parameterName,
		mixed $value,
	): Parameter
	{
		if ($value instanceof DoctrineParameterAware) {
			return $value->createDoctrineParameter($parameterName); // the value knows its type
		}

		$fieldMetadata = $metadata->getFieldMetadata($field);
		$columnType = self::findColumnType($fieldMetadata);
		if ($columnType === null) {
			return new Parameter($parameterName, $value);
		}

		$platform = $entityManager->getConnection()->getDatabasePlatform();
		$targetEntity = $fieldMetadata->isAssociation ? $fieldMetadata->getAssociationMapping()->targetEntity : null;

		if (is_array($value)) {
			return self::createForList($entityManager, $columnType, $platform, $targetEntity, $parameterName, $value);
		}

		$databaseValue = self::replaceEntityByIdentifier($entityManager, $targetEntity, $value);

		if (is_object($databaseValue) && !self::isBoundByDoctrine($databaseValue)) {
			$databaseValue = self::convertToDatabaseValue($columnType, $databaseValue, $platform);
			if (is_object($databaseValue)) {
				// The type of the column passes the object through, e.g. a Stringable compared with a string column.
				return new Parameter($parameterName, $value);
			}

			return new Parameter($parameterName, $databaseValue, $columnType->getBindingType());
		}

		if (is_string($databaseValue) && self::isBoundAsBinary($columnType)) {
			return new Parameter($parameterName, $databaseValue, $columnType->getBindingType());
		}

		return new Parameter($parameterName, $databaseValue);
	}

	/**
	 * Whether strings compared with the field have to be bound as binary values, text never equals a binary value
	 * in SQLite and is rejected by PostgreSQL.
	 */
	public static function isBinaryField(QueryMetadata $metadata, Field $field): bool
	{
		$columnType = self::findColumnType($metadata->getFieldMetadata($field));

		return $columnType !== null && self::isBoundAsBinary($columnType);
	}

	/**
	 * DBAL binds the items of a list as they are, all of them by one type. Doctrine converts a single object by its
	 * type but not the objects in a list, so they are converted by the type of the column here.
	 *
	 * @param class-string|null $targetEntity
	 * @param array<array-key, mixed> $values
	 */
	private static function createForList(
		EntityManagerInterface $entityManager,
		Type $columnType,
		AbstractPlatform $platform,
		?string $targetEntity,
		string $parameterName,
		array $values,
	): Parameter
	{
		$isBoundAsBinary = self::isBoundAsBinary($columnType);

		foreach ($values as $key => $value) {
			$value = self::replaceEntityByIdentifier($entityManager, $targetEntity, $value);

			if ($value instanceof BackedEnum) {
				if ($isBoundAsBinary) {
					$value = $value->value; // Doctrine converts enums only in a list without an explicit type
				}
			} else if (is_object($value)) {
				$value = self::convertToDatabaseValue($columnType, $value, $platform);
			}

			$values[$key] = $value;
		}

		if ($isBoundAsBinary) {
			return new Parameter($parameterName, $values, ArrayParameterType::BINARY);
		}

		return new Parameter($parameterName, $values); // integers or strings, by the first value
	}

	/**
	 * Doctrine replaces an entity by its identifier too, but it binds the identifier as a string whatever its type is.
	 *
	 * @param class-string|null $targetEntity Entity of the association the value is compared with
	 */
	private static function replaceEntityByIdentifier(EntityManagerInterface $entityManager, ?string $targetEntity, mixed $value): mixed
	{
		if ($targetEntity === null || !$value instanceof $targetEntity) {
			return $value;
		}

		return $entityManager->getUnitOfWork()->getSingleIdentifierValue($value)
			?? throw ORMInvalidArgumentException::invalidIdentifierBindingEntity($value::class);
	}

	/**
	 * Objects whose type Doctrine infers from the value itself, as it did before the type of the column was used.
	 */
	private static function isBoundByDoctrine(object $value): bool
	{
		return $value instanceof BackedEnum || $value instanceof DateTimeInterface || $value instanceof DateInterval;
	}

	private static function isBoundAsBinary(Type $columnType): bool
	{
		$bindingType = $columnType->getBindingType();

		return $bindingType === ParameterType::BINARY || $bindingType === ParameterType::LARGE_OBJECT;
	}

	/**
	 * Type of the column of the field or, for an association, of the identifier its join column references.
	 *
	 * @return Type|null Null when the field has no single column, e.g. an association by a composite foreign key
	 */
	private static function findColumnType(FieldMetadata $fieldMetadata): ?Type
	{
		try {
			return Type::getType($fieldMetadata->getFieldType()->type);
		} catch (UnsupportedFieldTypeException | MappingException) {
			return null;
		}
	}

	private static function convertToDatabaseValue(Type $type, object $value, AbstractPlatform $platform): mixed
	{
		try {
			return $type->convertToDatabaseValue($value, $platform);
		} catch (ConversionException $exception) {
			if ($value instanceof DateTimeInterface && !$value instanceof DateTimeImmutable) {
				// Immutable date types accept only DateTimeImmutable, while Doctrine accepts a single DateTime for them too.
				return $type->convertToDatabaseValue(DateTimeImmutable::createFromInterface($value), $platform);
			}

			throw $exception;
		}
	}

}
