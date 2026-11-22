<?php declare(strict_types = 1);

namespace Tests\Unit\Exception;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Exception\InvalidResultKeyException;
use Tests\Entity\Enum\ArticleType;
use Tests\TestCase;

final class InvalidResultKeyExceptionTest extends TestCase
{

	/**
	 * @return iterable<string, array{mixed, string}>
	 */
	public static function provideKeys(): iterable
	{
		yield 'null' => [null, 'got null. Exclude the rows with the criteria [\'author.role.id !=\' => null], or iterate over yield().'];
		yield 'object' => [new DateTimeImmutable(), 'got DateTimeImmutable. Use ScalarQueries to get the database value, or iterate over yield().'];
		yield 'enum' => [ArticleType::News, 'got Tests\Entity\Enum\ArticleType. Use ScalarQueries to get the database value, or iterate over yield().'];
		yield 'float' => [1.5, 'got float. Iterate over yield() instead.'];
		yield 'boolean' => [true, 'got bool. Iterate over yield() instead.'];
	}

	#[DataProvider('provideKeys')]
	public function testMessageAdvisesAccordingToTheKey(mixed $key, string $expectedEnding): void
	{
		$exception = new InvalidResultKeyException('author.role.id', $key);

		$this->assertSame('author.role.id', $exception->fieldName);
		$this->assertSame(
			sprintf('The key "author.role.id" must be a string or an integer to be used as an array key, %s', $expectedEnding),
			$exception->getMessage(),
		);
	}

}
