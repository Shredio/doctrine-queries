<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\TestCase;

final class CriteriaQueriesTest extends TestCase
{

	use ClockSensitiveTrait;
	use DoctrineContext;

	public function testFindByWithEmptyCriteria(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, [])->asArray();

		$this->assertCount(3, $values);
		$this->assertSame('Sample Article', $values[0]['title']);
		$this->assertSame('Another Article', $values[1]['title']);
		$this->assertSame('Third Article', $values[2]['title']);
	}

	public function testFindByWithSingleEqualsStringCriteria(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['title' => 'Sample Article'])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame('Sample Article', $values[0]['title']);
		$this->assertSame('This is a sample article.', $values[0]['content']);
	}

	public function testFindByWithSingleEqualsIntegerCriteria(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id' => 2])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame(2, $values[0]['id']);
		$this->assertSame('Another Article', $values[0]['title']);
	}

	public function testFindByWithExplicitEqualsOperator(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['title =' => 'Sample Article'])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame('Sample Article', $values[0]['title']);
	}

	public function testFindByWithNotEqualsOperator(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['title !=' => 'Sample Article'])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame('Another Article', $values[0]['title']);
		$this->assertSame('Third Article', $values[1]['title']);
	}

	public function testFindByWithGreaterThanOperator(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id >' => 1])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame(2, $values[0]['id']);
		$this->assertSame(3, $values[1]['id']);
	}

	public function testFindByWithGreaterThanOrEqualOperator(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id >=' => 2])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame(2, $values[0]['id']);
		$this->assertSame(3, $values[1]['id']);
	}

	public function testFindByWithLessThanOperator(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id <' => 3])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame(1, $values[0]['id']);
		$this->assertSame(2, $values[1]['id']);
	}

	public function testFindByWithLessThanOrEqualOperator(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id <=' => 2])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame(1, $values[0]['id']);
		$this->assertSame(2, $values[1]['id']);
	}

	public function testFindByWithLikeOperator(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['title LIKE' => '%Article%'])->asArray();

		$this->assertCount(3, $values);
		$this->assertSame('Sample Article', $values[0]['title']);
		$this->assertSame('Another Article', $values[1]['title']);
		$this->assertSame('Third Article', $values[2]['title']);
	}

	public function testFindByWithNotLikeOperator(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['title NOT LIKE' => '%Sample%'])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame('Another Article', $values[0]['title']);
		$this->assertSame('Third Article', $values[1]['title']);
	}

	public function testFindByWithNullValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['symbol' => null])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame('Another Article', $values[0]['title']);
		$this->assertSame('Third Article', $values[1]['title']);
		$this->assertNull($values[0]['symbol']);
		$this->assertNull($values[1]['symbol']);
	}

	public function testFindByWithNotNullValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['symbol !=' => null])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame('Sample Article', $values[0]['title']);
		$this->assertSame('sym', $values[0]['symbol']);
	}

	public function testFindByWithArrayValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id' => [1, 3]])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame(1, $values[0]['id']);
		$this->assertSame(3, $values[1]['id']);
		$this->assertSame('Sample Article', $values[0]['title']);
		$this->assertSame('Third Article', $values[1]['title']);
	}

	public function testFindByWithArrayValueNotIn(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id !=' => [1, 3]])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame(2, $values[0]['id']);
		$this->assertSame('Another Article', $values[0]['title']);
	}

	public function testFindByWithEmptyStringValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['title' => ''])->asArray();

		$this->assertCount(0, $values);
	}

	public function testFindByWithMultipleCriteria(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, [
			'id >' => 1,
			'title LIKE' => '%Article%'
		])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame(2, $values[0]['id']);
		$this->assertSame(3, $values[1]['id']);
		$this->assertSame('Another Article', $values[0]['title']);
		$this->assertSame('Third Article', $values[1]['title']);
	}

	public function testFindByWithComplexMultipleCriteria(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, [
			'id' => [1, 2],
			'symbol' => null,
			'title !=' => 'Non-existent Article'
		])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame(2, $values[0]['id']);
		$this->assertSame('Another Article', $values[0]['title']);
		$this->assertNull($values[0]['symbol']);
	}

	public function testFindByWithNumericStringValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id' => '2'])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame(2, $values[0]['id']);
		$this->assertSame('Another Article', $values[0]['title']);
	}

	public function testFindByWithBooleanLikeValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id >' => 0])->asArray();

		$this->assertCount(3, $values);
	}

	public function testFindByWithSpecialCharactersInValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['content LIKE' => '%sample%'])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame('Sample Article', $values[0]['title']);
	}

	public function testFindByWithMixedArrayTypes(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['title' => ['Sample Article', 'Third Article']])->asArray();

		$this->assertCount(2, $values);
		$this->assertSame('Sample Article', $values[0]['title']);
		$this->assertSame('Third Article', $values[1]['title']);
	}

	public function testFindByWithEmptyArrayValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id' => []])->asArray();

		$this->assertCount(0, $values);
	}

	public function testFindByWithSingleItemArrayValue(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id' => [2]])->asArray();

		$this->assertCount(1, $values);
		$this->assertSame(2, $values[0]['id']);
		$this->assertSame('Another Article', $values[0]['title']);
	}

	private function getQueries(): ScalarQueries
	{
		return new ScalarQueries(new SimplifiedQueryBuilderFactory($this->createManagerRegistry()));
	}

}
