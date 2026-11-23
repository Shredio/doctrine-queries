<?php declare(strict_types = 1);

namespace Tests\Unit\Aggregation;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Aggregation\AggregatedValueConverter;
use Shredio\DoctrineQueries\Aggregation\AggregateFunction;
use Shredio\DoctrineQueries\Exception\AggregationOverflowException;
use Shredio\DoctrineQueries\Exception\UnsupportedAggregationException;
use Shredio\DoctrineQueries\Metadata\FieldType;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\Enum\Priority;
use Tests\TestCase;

final class AggregatedValueConverterTest extends TestCase
{

	/**
	 * @return iterable<string, array{AggregateFunction, string, mixed, mixed}>
	 */
	public static function provideConvertedValues(): iterable
	{
		yield 'sum of integers' => [AggregateFunction::Sum, 'integer', 14, 14];
		yield 'sum of integers as a decimal string' => [AggregateFunction::Sum, 'integer', '207553', 207553];
		yield 'negative sum of integers as a string' => [AggregateFunction::Sum, 'smallint', '-3', -3];
		yield 'the highest integer as a string' => [AggregateFunction::Sum, 'bigint', (string) PHP_INT_MAX, PHP_INT_MAX];
		yield 'sum of decimals' => [AggregateFunction::Sum, 'decimal', '112.50', '112.50'];
		yield 'sum of decimals as a float' => [AggregateFunction::Sum, 'decimal', 112.5, '112.5'];
		yield 'sum of decimals as an integer' => [AggregateFunction::Sum, 'decimal', 15, '15'];
		yield 'sum of floats' => [AggregateFunction::Sum, 'float', 1.75, 1.75];
		yield 'sum of floats as a string' => [AggregateFunction::Sum, 'float', '1.75', 1.75];
		yield 'sum of floats as an integer' => [AggregateFunction::Sum, 'float', 2, 2.0];
		yield 'sum of no value' => [AggregateFunction::Sum, 'integer', null, null];
		yield 'minimum of integers as a string' => [AggregateFunction::Min, 'integer', '7', 7];
		yield 'maximum of booleans' => [AggregateFunction::Max, 'boolean', 1, true];
		yield 'maximum of strings' => [AggregateFunction::Max, 'string', 'b', 'b'];
		yield 'minimum of no value' => [AggregateFunction::Min, 'datetime_immutable', null, null];
	}

	#[DataProvider('provideConvertedValues')]
	public function testConvert(AggregateFunction $function, string $doctrineType, mixed $value, mixed $expected): void
	{
		$this->assertSame($expected, self::createConverter($function, $doctrineType)->convert($value));
	}

	public function testMinimumOfDatesIsConvertedToAnObject(): void
	{
		$date = self::createConverter(AggregateFunction::Min, 'datetime_immutable')->convert('2021-01-02 03:04:05');

		$this->assertInstanceOf(\DateTimeImmutable::class, $date);
		$this->assertSame('2021-01-02 03:04:05', $date->format('Y-m-d H:i:s'));
	}

	public function testEnumFieldIsConvertedToTheEnum(): void
	{
		$this->assertSame(ArticleType::Blog, self::createConverter(AggregateFunction::Min, 'string', ArticleType::class)->convert('blog'));
		$this->assertSame(Priority::High, self::createConverter(AggregateFunction::Max, 'smallint', Priority::class)->convert('2'));
		$this->assertSame(3, self::createConverter(AggregateFunction::Sum, 'smallint', Priority::class)->convert(3), 'A sum is not a case of the enum.');
	}

	/**
	 * @return iterable<string, array{mixed}>
	 */
	public static function provideOverflowingSums(): iterable
	{
		yield 'above the highest integer' => ['9223372036854775808'];
		yield 'below the lowest integer' => ['-9223372036854775809'];
		yield 'a float' => [9.2233720368547758E+18];
		yield 'a fraction' => ['1.5'];
	}

	#[DataProvider('provideOverflowingSums')]
	public function testSumOfIntegersThatIsNotAnIntegerOverflows(mixed $value): void
	{
		$this->expectException(AggregationOverflowException::class);
		$this->expectExceptionMessage('SUM() of Entity::$field returned');

		self::createConverter(AggregateFunction::Sum, 'bigint')->convert($value);
	}

	/**
	 * @return iterable<string, array{string, mixed}>
	 */
	public static function provideUnexpectedSums(): iterable
	{
		yield 'decimal that is not a number' => ['decimal', 'abc'];
		yield 'decimal as a boolean' => ['decimal', true];
		yield 'float that is not a number' => ['float', 'abc'];
	}

	#[DataProvider('provideUnexpectedSums')]
	public function testUnexpectedSumIsRejected(string $doctrineType, mixed $value): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('SUM() of Entity::$field returned an unexpected value of the type');

		self::createConverter(AggregateFunction::Sum, $doctrineType)->convert($value);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideTypesThatCannotBeSummed(): iterable
	{
		yield 'string' => ['string'];
		yield 'date' => ['date_immutable'];
		yield 'boolean' => ['boolean'];
		yield 'not registered' => ['not_registered_type'];
	}

	#[DataProvider('provideTypesThatCannotBeSummed')]
	public function testSumOfOtherTypeIsNotSupported(string $doctrineType): void
	{
		$this->expectException(UnsupportedAggregationException::class);
		$this->expectExceptionMessage(sprintf('the field has the type "%s"', $doctrineType));

		self::createConverter(AggregateFunction::Sum, $doctrineType);
	}

	/**
	 * @param class-string<\BackedEnum>|null $enumType
	 */
	private static function createConverter(AggregateFunction $function, string $doctrineType, ?string $enumType = null): AggregatedValueConverter
	{
		return new AggregatedValueConverter(
			$function,
			new FieldType($doctrineType, false, $enumType, false),
			new SQLitePlatform(),
			sprintf('%s() of Entity::$field', $function->value),
		);
	}

}
