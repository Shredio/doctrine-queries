<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
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
		$values = $queries->findBy(Article::class)->asArray();

		$this->assertCount(3, $values);

		foreach ($values as $value) {
			$this->assertInstanceOf(Article::class, $value);
		}
	}

	public function testFindOneBy(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$value = $queries->findOneBy(Article::class);

		$this->assertNotNull($value);
	}

	public function testFindOneByReturnNull(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$value = $queries->findOneBy(Article::class, criteria: ['author' => 'NonExistent']);

		$this->assertNull($value);
	}

	private function getQueries(): ObjectQueries
	{
		return new ObjectQueries(new SimplifiedQueryBuilderFactory($this->createManagerRegistry()));
	}

}
