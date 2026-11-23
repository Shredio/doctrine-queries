<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use LogicException;

final class AccountNumberType extends Type
{

	public const string Name = 'account_number';

	public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
	{
		return $platform->getIntegerTypeDeclarationSQL($column);
	}

	public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AccountNumber
	{
		return match (true) {
			$value === null => null,
			is_int($value) || is_string($value) => new AccountNumber((int) $value), // a driver can return integers as strings
			default => throw new LogicException('Not supported.'),
		};
	}

	public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
	{
		return match (true) {
			$value === null => null,
			$value instanceof AccountNumber => $value->value,
			is_int($value) => $value,
			default => throw InvalidType::new($value, self::Name, ['null', 'int', AccountNumber::class]),
		};
	}

	public function getBindingType(): ParameterType
	{
		return ParameterType::INTEGER;
	}

}
