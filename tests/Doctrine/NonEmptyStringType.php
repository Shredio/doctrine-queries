<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\StringType;

/**
 * A string column that refuses an empty string in both directions, so its values are non-empty-string.
 * PHPStan learns the type from NonEmptyStringTypeDescriptor.
 */
final class NonEmptyStringType extends StringType
{

	public const string Name = 'non_empty_string';

	public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
	{
		$value = parent::convertToPHPValue($value, $platform);
		if ($value === '') {
			throw ValueNotConvertible::new($value, 'non-empty-string');
		}

		return $value;
	}

	public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
	{
		if ($value === '') {
			throw InvalidType::new($value, self::Name, ['null', 'non-empty-string']);
		}

		return parent::convertToDatabaseValue($value, $platform);
	}

}
