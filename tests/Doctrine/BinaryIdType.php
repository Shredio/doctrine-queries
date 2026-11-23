<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use LogicException;

final class BinaryIdType extends Type
{

	public const string Name = 'binary_id';

	public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
	{
		return $platform->getBinaryTypeDeclarationSQL(['length' => 16, 'fixed' => true]);
	}

	public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BinaryId
	{
		return match (true) {
			$value === null => null,
			is_resource($value) => new BinaryId((string) stream_get_contents($value)),
			is_string($value) => new BinaryId($value),
			default => throw new LogicException('Not supported.'),
		};
	}

	public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
	{
		return match (true) {
			$value === null => null,
			$value instanceof BinaryId => $value->bytes,
			is_string($value) => $value,
			default => throw InvalidType::new($value, self::Name, ['null', 'string', BinaryId::class]),
		};
	}

	public function getBindingType(): ParameterType
	{
		return ParameterType::BINARY;
	}

}
