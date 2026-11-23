<?php declare(strict_types = 1);

namespace Tests\Unit\Criteria;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Criteria\Criteria;
use Tests\TestCase;

final class CriteriaTest extends TestCase
{

	public function testAnyOfKeepsTheAlternativesInOrder(): void
	{
		$anyOf = Criteria::anyOf(['id' => 1], ['title LIKE' => '%a%', 'symbol' => null]);

		$this->assertSame([['id' => 1], ['title LIKE' => '%a%', 'symbol' => null]], $anyOf->alternatives);
	}

	public function testAnyOfWithSingleAlternative(): void
	{
		$this->assertSame([['id' => 1]], Criteria::anyOf(['id' => 1])->alternatives);
	}

	public function testAlternativesAreAList(): void
	{
		$this->assertSame([['id' => 1], ['id' => 2]], Criteria::anyOf(first: ['id' => 1], second: ['id' => 2])->alternatives);
		$this->assertSame([['id' => 1], ['id' => 2]], Criteria::anyOf(...[3 => ['id' => 1], 7 => ['id' => 2]])->alternatives);
	}

	public function testNestedGroupIsKept(): void
	{
		$nested = Criteria::anyOf(['id' => 2], ['id' => 3]);
		$anyOf = Criteria::anyOf(['id' => 1], ['symbol' => null, $nested]);

		$this->assertSame([['id' => 1], ['symbol' => null, $nested]], $anyOf->alternatives);
	}

	public function testAlternativeWithOnlyANestedGroupIsNotEmpty(): void
	{
		$nested = Criteria::anyOf(['id' => 2]);

		$this->assertSame([[$nested]], Criteria::anyOf([$nested])->alternatives);
	}

	public function testAnyOfWithoutAlternativesIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Criteria::anyOf() needs at least one alternative.');

		Criteria::anyOf();
	}

	public function testAnyOfWithUnpackedEmptyListIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Criteria::anyOf() needs at least one alternative.');

		Criteria::anyOf(...[]);
	}

	/**
	 * @return iterable<string, array{list<array<array-key, mixed>>, int}>
	 */
	public static function provideAlternativesWithEmptyOne(): iterable
	{
		yield 'only alternative' => [[[]], 1];
		yield 'first alternative' => [[[], ['id' => 1]], 1];
		yield 'second alternative' => [[['id' => 1], []], 2];
		yield 'third alternative' => [[['id' => 1], ['id' => 2], [], ['id' => 3]], 3];
	}

	/**
	 * @param list<array<array-key, mixed>> $alternatives
	 */
	#[DataProvider('provideAlternativesWithEmptyOne')]
	public function testAlternativeWithoutCriteriaIsRejected(array $alternatives, int $expectedPosition): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage(sprintf('Alternative #%d of Criteria::anyOf() has no criteria.', $expectedPosition));

		Criteria::anyOf(...$alternatives);
	}

	public function testNamedAlternativeWithoutCriteriaIsRejectedByItsPosition(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Alternative #2 of Criteria::anyOf() has no criteria.');

		Criteria::anyOf(first: ['id' => 1], second: []);
	}

}
