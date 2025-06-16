<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\TestManagerRegistry;
use Tests\Entity\Article;
use Tests\TestCase;

final class QueriesTest extends TestCase
{

	use ClockSensitiveTrait;
	use DoctrineContext;

	public function testCriteria(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->scalars->findBy(Article::class, ['title' => 'Sample Article'])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame('Sample Article', $values[0]['title']);
	}

	public function testSelect(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->scalars->findBy(Article::class, select: ['id', 'title'])->asArray();

		$this->assertCount(3, $values);
		$this->assertSame([
			['id' => 1, 'title' => 'Sample Article'],
			['id' => 2, 'title' => 'Another Article'],
			['id' => 3, 'title' => 'Third Article'],
		], $values);
	}

	public function testSelectAlias(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->scalars->findBy(Article::class, select: ['id' => 'articleId', 'title' => 'articleTitle'])->asArray();

		$this->assertCount(3, $values);
		$this->assertSame([
			['articleId' => 1, 'articleTitle' => 'Sample Article'],
			['articleId' => 2, 'articleTitle' => 'Another Article'],
			['articleId' => 3, 'articleTitle' => 'Third Article'],
		], $values);
	}

	public function testOrderByAsc(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->scalars->findBy(Article::class, orderBy: ['id' => 'ASC'], select: ['id', 'title'])->asArray();

		$this->assertCount(3, $values);
		$this->assertSame([
			['id' => 1, 'title' => 'Sample Article'],
			['id' => 2, 'title' => 'Another Article'],
			['id' => 3, 'title' => 'Third Article'],
		], $values);
	}

	public function testOrderByDesc(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->scalars->findBy(Article::class, orderBy: ['id' => 'DESC'], select: ['id', 'title'])->asArray();

		$this->assertCount(3, $values);
		$this->assertSame([
			['id' => 3, 'title' => 'Third Article'],
			['id' => 2, 'title' => 'Another Article'],
			['id' => 1, 'title' => 'Sample Article'],
		], $values);
	}

	public function testCount(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();
		$count = $queries->countBy(Article::class);

		$this->assertSame(3, $count);
	}

	public function testCountCriteria(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();
		$count = $queries->countBy(Article::class, ['id' => 1]);

		$this->assertSame(1, $count);
	}

	public function testCountZero(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();
		$count = $queries->countBy(Article::class, ['id' => 0]);

		$this->assertSame(0, $count);
	}

	public function testExists(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();
		$exists = $queries->existsBy(Article::class, ['id' => 1]);

		$this->assertTrue($exists);
	}

	public function testNotExists(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();
		$exists = $queries->existsBy(Article::class, ['id' => 0]);

		$this->assertFalse($exists);
	}

	private function getQueries(): DoctrineQueries
	{
		return new DoctrineQueries(new TestManagerRegistry($this->getEntityManager()));
	}

}
