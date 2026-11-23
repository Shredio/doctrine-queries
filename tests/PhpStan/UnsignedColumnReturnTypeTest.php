<?php declare(strict_types = 1);

namespace Tests\PhpStan;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The integers of an integer column mapped as unsigned are inferred as non-negative in the results of scalar
 * and array queries.
 */
final class UnsignedColumnReturnTypeTest extends TypeInferenceTestCase
{

	/**
	 * @return iterable<mixed>
	 */
	public static function dataFileAsserts(): iterable
	{
		yield from self::gatherAssertTypes(__DIR__ . '/Data/UnsignedColumnCases.php');
	}

	#[DataProvider('dataFileAsserts')]
	public function testFileAsserts(
		string $assertType,
		string $file,
		mixed ...$args
	): void
	{
		$this->assertFileAsserts($assertType, $file, ...$args);
	}

	/**
	 * @return string[]
	 */
	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/phpstan-return-types.neon'];
	}

}
