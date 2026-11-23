<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use PHPStan\Type\Accessory\AccessoryNonEmptyStringType;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

/**
 * Describes NonEmptyStringType as non-empty-string in every direction, also as the database returns it.
 * A ReflectionDescriptor would take the database type from the parent StringType, so scalar queries would return string.
 */
final readonly class NonEmptyStringTypeDescriptor implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return NonEmptyStringType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return self::createNonEmptyString();
	}

	public function getWritableToDatabaseType(): Type
	{
		return self::createNonEmptyString();
	}

	public function getDatabaseInternalType(): Type
	{
		return self::createNonEmptyString();
	}

	private static function createNonEmptyString(): Type
	{
		return new IntersectionType([new StringType(), new AccessoryNonEmptyStringType()]);
	}

}
