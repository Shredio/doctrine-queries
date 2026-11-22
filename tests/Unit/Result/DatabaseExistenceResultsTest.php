<?php declare(strict_types = 1);

namespace Tests\Unit\Result;

use InvalidArgumentException;
use Shredio\DoctrineQueries\Result\DatabaseExistenceResults;
use stdClass;
use Tests\Entity\Enum\ArticleType;
use Tests\TestCase;

final class DatabaseExistenceResultsTest extends TestCase
{

	public function testEmptyResults(): void
	{
		$results = new DatabaseExistenceResults([]);

		$this->assertFalse($results->has(['id' => 1]));
		$this->assertFalse($results->has([]), 'Keys cannot be checked without results.');
	}

	public function testValuesAreComparedLikeInTheDatabase(): void
	{
		$results = new DatabaseExistenceResults([
			['id' => 1, 'active' => true, 'type' => 'news', 'note' => null],
			['id' => 2, 'active' => false, 'type' => 'blog', 'note' => ''],
		]);

		$this->assertTrue($results->has(['id' => 1, 'active' => true, 'type' => 'news', 'note' => null]));
		$this->assertTrue($results->has(['id' => '1', 'active' => 1, 'type' => ArticleType::News, 'note' => null]));
		$this->assertTrue($results->has(['note' => '', 'type' => 'blog', 'active' => 0, 'id' => 2]), 'The order of the keys does not matter.');
		$this->assertFalse($results->has(['id' => 1, 'active' => true, 'type' => 'news', 'note' => '']), 'NULL is not an empty string.');
		$this->assertFalse($results->has(['id' => 2, 'active' => false, 'type' => 'blog', 'note' => null]), 'An empty string is not NULL.');
		$this->assertFalse($results->has(['id' => 1, 'active' => false, 'type' => 'news', 'note' => null]));
	}

	public function testMissingKey(): void
	{
		$results = new DatabaseExistenceResults([['id' => 1, 'note' => null]]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Missing key "note" in values array.');

		$results->has(['id' => 1]);
	}

	public function testUncomparableValue(): void
	{
		$results = new DatabaseExistenceResults([['id' => 1]]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Value of type stdClass cannot be compared');

		$results->has(['id' => new stdClass()]); // @phpstan-ignore argument.type
	}

	public function testAcceptsIterables(): void
	{
		$results = new DatabaseExistenceResults((static function (): \Generator {
			yield ['id' => 1];
			yield ['id' => 2];
		})());

		$this->assertTrue($results->has(['id' => 2]));
		$this->assertFalse($results->has(['id' => 3]));
	}

}
