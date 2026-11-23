<?php declare(strict_types = 1);

namespace Tests\Unit\Parameter;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Parameter\CriteriaParameterFactory;
use Tests\Doctrine\AccountNumberType;
use Tests\Doctrine\BinaryIdType;
use Tests\Doctrine\NonEmptyStringType;
use Tests\Doctrine\SymbolType;
use Tests\Factory\EntityManagerFactory;
use Tests\TestCase;

final class CriteriaParameterFactoryTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, bool}>
	 */
	public static function provideTypes(): iterable
	{
		// The built-in scalar types pass an object through, the driver binds it by its __toString().
		yield 'string' => ['string', false];
		yield 'text' => ['text', false];
		yield 'integer' => ['integer', false];
		yield 'decimal' => ['decimal', false];
		yield 'boolean' => ['boolean', false];
		yield 'binary' => ['binary', false];
		yield 'json encodes anything' => ['json', false];
		yield 'custom type extending a string type leniently' => [NonEmptyStringType::Name, false];

		yield 'date' => ['date_immutable', true];
		yield 'datetime' => ['datetime', true];
		yield 'time' => ['time_immutable', true];
		yield 'date interval' => ['dateinterval', true];
		yield 'custom type of a value object' => [AccountNumberType::Name, true];
		yield 'custom binary type of a value object' => [BinaryIdType::Name, true];
		yield 'custom type throwing another exception' => [SymbolType::Name, true];
	}

	#[DataProvider('provideTypes')]
	public function testRejectsUnknownObjects(string $typeName, bool $expected): void
	{
		EntityManagerFactory::createInMemory(); // registers the custom types

		$this->assertSame($expected, CriteriaParameterFactory::rejectsUnknownObjects(Type::getType($typeName), new SQLitePlatform()));
	}

}
