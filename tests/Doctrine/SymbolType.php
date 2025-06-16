<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class SymbolType extends StringType
{

	public const string Name = 'symbol';

	public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
	{
		$column['length'] = 30;

		return parent::getSQLDeclaration($column, $platform);
	}

	public function convertToPHPValue($value, AbstractPlatform $platform): mixed
	{
		if (is_string($value)) {
			return new Symbol($value);
		}

		return parent::convertToPHPValue($value, $platform);
	}

	public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
	{
		if ($value instanceof Symbol) {
			return $value->value;
		}

		return parent::convertToDatabaseValue($value, $platform);
	}

	public function getName(): string
	{
		return self::Name;
	}

}
