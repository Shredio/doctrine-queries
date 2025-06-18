<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;
use LogicException;

final class SymbolType extends StringType
{

	public const string Name = 'symbol';

	public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
	{
		$column['length'] = 30;

		return parent::getSQLDeclaration($column, $platform);
	}

	public function convertToPHPValue($value, AbstractPlatform $platform): ?Symbol
	{
		if ($value === null) {
			return null;
		}

		if (is_string($value)) {
			return new Symbol($value);
		}

		throw new LogicException('Not supported.');
	}

	public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
	{
		if ($value === null) {
			return null;
		}

		if ($value instanceof Symbol) {
			return $value->value;
		}

		throw new LogicException('Not supported.');
	}

	public function getName(): string
	{
		return self::Name;
	}

}
