<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Aggregation;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;
use Shredio\DoctrineQueries\Exception\AggregationOverflowException;
use Shredio\DoctrineQueries\Exception\UnsupportedAggregationException;
use Shredio\DoctrineQueries\Metadata\FieldType;

/**
 * Converts the value an aggregate function returns. Doctrine does not convert the results of functions, they are
 * returned as the driver fetches them.
 *
 * MIN() and MAX() return a value of the field, it is converted by the type of the field as a selected field is.
 * SUM() is returned by the kind of the field, see SumKind.
 *
 * @internal
 */
final readonly class AggregatedValueConverter
{

	private ?SumKind $sumKind;

	/**
	 * @param string $aggregation Describes the aggregation in the messages of the exceptions, e.g. "SUM() of App\Entity\Order::$price"
	 *
	 * @throws UnsupportedAggregationException
	 */
	public function __construct(
		private AggregateFunction $function,
		private FieldType $fieldType,
		private AbstractPlatform $platform,
		private string $aggregation,
	)
	{
		$this->sumKind = $function === AggregateFunction::Sum ? SumKind::fromDoctrineType($fieldType->type) : null;

		if ($function === AggregateFunction::Sum && $this->sumKind === null) {
			throw new UnsupportedAggregationException(sprintf(
				'%s is not supported, the field has the type "%s". Only integer, decimal and float fields can be summed.',
				$aggregation,
				$fieldType->type,
			));
		}
	}

	/**
	 * @throws AggregationOverflowException
	 */
	public function convert(mixed $value): mixed
	{
		if ($value === null) {
			return null; // no row, or only NULL values
		}

		return match ($this->sumKind) {
			SumKind::Integer => $this->toInteger($value),
			SumKind::Decimal => $this->toNumericString($value),
			SumKind::Float => $this->toFloat($value),
			null => $this->toFieldValue($value),
		};
	}

	private function toInteger(mixed $value): int
	{
		if (is_int($value)) {
			return $value;
		}

		// e.g. MySQL sums integers to a decimal, which the drivers return as a string
		$integer = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
		if ($integer === false) {
			throw new AggregationOverflowException(sprintf(
				'%s returned %s, which is not an integer PHP can represent.',
				$this->aggregation,
				is_scalar($value) ? var_export($value, true) : get_debug_type($value),
			));
		}

		return $integer;
	}

	/**
	 * @return numeric-string
	 */
	private function toNumericString(mixed $value): string
	{
		if (is_int($value) || is_float($value)) {
			$value = (string) $value; // e.g. SQLite has no decimal type
		}

		if (!is_string($value) || !is_numeric($value)) {
			throw $this->unexpectedValue($value);
		}

		return $value;
	}

	private function toFloat(mixed $value): float
	{
		if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) { // e.g. pdo_pgsql returns a string
			return (float) $value;
		}

		throw $this->unexpectedValue($value);
	}

	private function toFieldValue(mixed $value): mixed
	{
		$value = Type::getType($this->fieldType->type)->convertToPHPValue($value, $this->platform);
		$enumType = $this->fieldType->enumType;

		if ($enumType !== null && (is_int($value) || is_string($value))) {
			return $enumType::from($value);
		}

		return $value;
	}

	private function unexpectedValue(mixed $value): LogicException
	{
		return new LogicException(sprintf('%s returned an unexpected value of the type %s.', $this->aggregation, get_debug_type($value)));
	}

}
