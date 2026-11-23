<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Shredio\DoctrineQueries\Aggregation\SummableType;

/**
 * A decimal column read as a float. It extends no numeric DBAL type, its fields can be summed only because
 * it implements SummableType, and their sum is a float as the fields are.
 */
final class FloatDecimalType extends Type implements SummableType
{

	public const string Name = 'float_decimal';

	public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
	{
		return $platform->getDecimalTypeDeclarationSQL($column);
	}

	public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
	{
		return match (true) {
			$value === null => null,
			is_float($value) || is_int($value) => (string) $value,
			is_string($value) && is_numeric($value) => $value,
			default => throw InvalidType::new($value, self::Name, ['null', 'float', 'int', 'numeric-string']),
		};
	}

	public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?float
	{
		return match (true) {
			$value === null => null,
			is_float($value) || is_int($value) || (is_string($value) && is_numeric($value)) => (float) $value,
			default => throw ValueNotConvertible::new($value, self::Name),
		};
	}

}
