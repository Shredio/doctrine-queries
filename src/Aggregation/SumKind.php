<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Aggregation;

use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\Type;

/**
 * The kind of a field that can be summed. The databases return a sum in a type of their own (MySQL sums integers
 * to a decimal string), so the sum is returned by the kind of the field: an integer, a numeric string or a float.
 *
 * @internal
 */
enum SumKind
{

	case Integer;
	case Decimal;
	case Float;

	/** Types added in later versions of DBAL 4, compared by name so that the older versions are still supported. */
	private const string NumberType = 'Doctrine\DBAL\Types\NumberType';
	private const string SmallFloatType = 'Doctrine\DBAL\Types\SmallFloatType';

	/**
	 * @return bool|null Null when the type is not registered, so it is not known
	 */
	public static function canBeSummed(string $typeName): ?bool
	{
		return Type::hasType($typeName) ? self::fromDoctrineType($typeName) !== null : null;
	}

	/**
	 * @return self|null Null for a type that cannot be summed, or that is not registered
	 */
	public static function fromDoctrineType(string $typeName): ?self
	{
		if (!Type::hasType($typeName)) {
			return null;
		}

		$type = Type::getType($typeName);

		return match (true) {
			$type instanceof IntegerType, $type instanceof SmallIntType, $type instanceof BigIntType => self::Integer,
			$type instanceof DecimalType, is_a($type, self::NumberType) => self::Decimal,
			$type instanceof FloatType, is_a($type, self::SmallFloatType) => self::Float,
			default => null,
		};
	}

}
