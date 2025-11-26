<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\MappingException;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\TestCase;
use Tests\Unit\Helpers;

final class ObjectQueriesTest extends TestCase
{

	use ClockSensitiveTrait;
	use DoctrineContext;
	use Helpers;

	public function testFindBy(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$values = $queries->findBy(Article::class)->asArray();

		$this->assertCount(3, $values);

		foreach ($values as $value) {
			$this->assertInstanceOf(Article::class, $value);
		}

		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindOneBy(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$value = $queries->findOneBy(Article::class);

		$this->assertNotNull($value);
		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindOneByReturnNull(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$value = $queries->findOneBy(Article::class, criteria: ['author' => 'NonExistent']);

		$this->assertNull($value);
		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindByWithJoins(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$values = $queries->findBy(Article::class, ['author.name' => 'Jane Smith'])->asArray();

		$this->assertCount(1, $values);
		$this->assertInstanceOf(Article::class, $values[0]);
		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindByWithNullableRelations(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$values = $queries->findBy(Article::class, ['author.role.id' => null], [], joinConfig: ['author.role' => 'left'])->asArray();

		$this->assertCount(2, $values);
		foreach ($values as $value) {
			$this->assertInstanceOf(Article::class, $value);
		}
		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindByWithNonNullableRelations(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$values = $queries->findBy(Article::class, ['author.role.id' => 1], joinConfig: 'inner')->asArray();

		$this->assertCount(1, $values);
		foreach ($values as $value) {
			$this->assertInstanceOf(Article::class, $value);
		}
		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindByWithNonNullableRelationsArray(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$values = $queries->findBy(Article::class, ['author.role.id' => 1], [], joinConfig: ['author.role' => 'inner'])->asArray();

		$this->assertCount(1, $values);
		foreach ($values as $value) {
			$this->assertInstanceOf(Article::class, $value);
		}
		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindOneByWithJoins(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$value = $queries->findOneBy(Article::class, ['author.name' => 'Jane Smith']);

		$this->assertNotNull($value);
		$this->assertInstanceOf(Article::class, $value);
		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindOneByWithNullableRelations(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$value = $queries->findOneBy(Article::class, ['author.role.id' => null], [], ['author.role' => 'left']);

		$this->assertNotNull($value);
		$this->assertInstanceOf(Article::class, $value);
		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindOneByWithNonNullableRelations(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$queryCount = $this->captureQueryCount();
		$value = $queries->findOneBy(Article::class, ['author.role.id' => 1], [], 'inner');

		$this->assertNotNull($value);
		$this->assertInstanceOf(Article::class, $value);
		$this->assertSame(1, $queryCount(), 'Expected only one query to be executed.');
	}

	public function testFindByWithPreloading(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$getQueryCount = $this->captureQueryCount();
		$values = $queries->findBy(Article::class, preload: ['author'])->asArray();

		$authorNames = array_map(fn(Article $article) => $article->getAuthor()->getName(), $values);

		$this->assertSame(['John Doe', 'Jane Smith', 'John Doe'], $authorNames);
		$this->assertSame(2, $getQueryCount(), 'Expected one query for articles and one query for authors.');
	}

	public function testFindByWithNestedPreloading(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$getQueryCount = $this->captureQueryCount();
		$values = $queries->findBy(Article::class, preload: ['author.role'])->asArray();

		$roleNames = array_map(fn(Article $article) => $article->getAuthor()->getRole()?->getName(), $values);

		$this->assertSame([null, 'Administrator', null], $roleNames);
		$this->assertSame(3, $getQueryCount(), 'Expected one query for articles, one query for authors and one query for roles.');
	}

	public function testFindByWithPreloadFieldWhichDoesNotExist(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();

		self::expectException(MappingException::class);
		$queries->findBy(Article::class, preload: ['nonExistent'])->asArray();
	}

	public function testFindByWithPreloadFieldInsteadOfAssociation(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();

		self::expectException(MappingException::class);
		$queries->findBy(Article::class, preload: ['title'])->asArray();
	}

	public function testFindByWithAssociationLazyFetch(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$getQueryCount = $this->captureQueryCount();
		$values = $queries->findBy(Article::class)->asArray();

		$authorNames = array_map(fn(Article $article) => $article->getAuthor()->getName(), $values);

		$this->assertSame(['John Doe', 'Jane Smith', 'John Doe'], $authorNames);

		$this->assertSame(3, $getQueryCount(), 'Expected one query for articles and two queries for authors.');
	}

	private function getQueries(): ObjectQueries
	{
		return new ObjectQueries(new SimplifiedQueryBuilderFactory($this->createManagerRegistry()));
	}

}
