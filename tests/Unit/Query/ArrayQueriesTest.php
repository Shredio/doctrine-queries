<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\Symbol;
use Tests\Entity\Article;
use Tests\TestCase;
use Tests\Unit\Helpers;

final class ArrayQueriesTest extends TestCase
{

	use ClockSensitiveTrait;
	use DoctrineContext;
	use Helpers;

	public function testFindBy(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class)->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));

		$this->assertNotEmpty(array_column($values, 'createdAt'));
		foreach (array_column($values, 'createdAt') as $value) {
			$this->assertInstanceOf(DateTimeImmutable::class, $value);
		}

		$this->assertNotEmpty($columns = array_column($values, 'symbol'));
		$this->assertInstanceOf(Symbol::class, $columns[0]);
		$this->assertNull($columns[1]);
		$this->assertNull($columns[2]);
	}

	public function testFindByWithRelations(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findByWithRelations(Article::class)->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
				'author' => 1,
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'author' => 2,
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'author' => 1,
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));

		$this->assertNotEmpty(array_column($values, 'createdAt'));
		foreach (array_column($values, 'createdAt') as $value) {
			$this->assertInstanceOf(DateTimeImmutable::class, $value);
		}

		$this->assertNotEmpty($columns = array_column($values, 'symbol'));
		$this->assertInstanceOf(Symbol::class, $columns[0]);
		$this->assertNull($columns[1]);
		$this->assertNull($columns[2]);
	}

	public function testFindIndexedByYield(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = iterator_to_array($queries->findIndexedBy(Article::class, 'title')->yield());

		$this->assertSame([
			'Sample Article' => [
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
			],
			'Another Article' => [
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
			],
			'Third Article' => [
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));
	}

	public function testFindIndexedBy(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findIndexedBy(Article::class, 'title')->asArray();

		$this->assertSame([
			'Sample Article' => [
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
			],
			'Another Article' => [
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
			],
			'Third Article' => [
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));
	}

	public function testFindIndexedByUnsetIndexField(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findIndexedBy(Article::class, 'title', select: ['id'])->asArray();

		$this->assertSame([
			'Sample Article' => [
				'id' => 1,
			],
			'Another Article' => [
				'id' => 2,
			],
			'Third Article' => [
				'id' => 3,
			],
		], $values);
	}

	private function getQueries(): ArrayQueries
	{
		return new ArrayQueries(new SimplifiedQueryBuilderFactory($this->createManagerRegistry()));
	}

}
