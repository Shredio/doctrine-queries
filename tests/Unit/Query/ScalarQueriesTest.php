<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\TestCase;
use Tests\Unit\Helpers;

final class ScalarQueriesTest extends TestCase
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
				'symbol' => 'sym',
				'createdAt' => '2021-01-01 00:00:00',
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
			],
		], $values);
	}

	public function testFindByYield(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = iterator_to_array($queries->findBy(Article::class)->yield());

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
				'symbol' => 'sym',
				'createdAt' => '2021-01-01 00:00:00',
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
			],
		], $values);
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
				'symbol' => 'sym',
				'createdAt' => '2021-01-01 00:00:00',
				'author' => 1,
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'author' => 2,
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'author' => 1,
			],
		], $values);
	}

	public function testFindByWithRelationsYield(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = iterator_to_array($queries->findByWithRelations(Article::class)->yield());

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
				'symbol' => 'sym',
				'createdAt' => '2021-01-01 00:00:00',
				'author' => 1,
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'author' => 2,
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'author' => 1,
			],
		], $values);
	}

	public function testFindPairs(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findPairsBy(Article::class, 'id', 'title')->asArray();

		$this->assertSame([
			1 => 'Sample Article',
			'Another Article',
			'Third Article',
		], $values);
	}

	public function testFindPairsSameKeyAsValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findPairsBy(Article::class, 'title', 'title')->asArray();

		$this->assertSame([
			'Sample Article' => 'Sample Article',
			'Another Article' => 'Another Article',
			'Third Article' => 'Third Article',
		], $values);
	}

	public function testFindColumnValues(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'title')->asArray();

		$this->assertSame([
			'Sample Article',
			'Another Article',
			'Third Article',
		], $values);
	}

	public function testFindSingleColumnValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$value = $queries->findSingleColumnValueBy(Article::class, 'title', ['id' => 1]);

		$this->assertSame('Sample Article', $value);
	}

	private function getQueries(): ScalarQueries
	{
		return new ScalarQueries(new SimplifiedQueryBuilderFactory($this->createManagerRegistry()));
	}

}
