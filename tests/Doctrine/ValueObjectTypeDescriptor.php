<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Doctrine\DBAL\Types\Type as DbalType;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Describes a custom DBAL type of a value object: the value object is read from the database and written to it,
 * the stored value can be written too. A ReflectionDescriptor would take mixed from convertToDatabaseValue().
 */
final readonly class ValueObjectTypeDescriptor implements DoctrineTypeDescriptor
{

	/**
	 * @param class-string<DbalType> $typeClass
	 * @param class-string $valueObjectClass
	 */
	public function __construct(
		private string $typeClass,
		private string $valueObjectClass,
		private Type $databaseType,
	)
	{
	}

	public function getType(): string
	{
		return $this->typeClass;
	}

	public function getWritableToPropertyType(): Type
	{
		return new ObjectType($this->valueObjectClass);
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(new ObjectType($this->valueObjectClass), $this->databaseType);
	}

	public function getDatabaseInternalType(): Type
	{
		return $this->databaseType;
	}

}
