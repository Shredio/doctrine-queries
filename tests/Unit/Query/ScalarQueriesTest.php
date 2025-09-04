<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\Entity\Enum\ArticleType;
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
				'type' => 'news',
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
		], $values);
	}

	public function testFindByWithJoins(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, criteria: ['author.name' => 'Jane Smith'])->asArray();

		$this->assertSame([
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'type' => 'news',
			],
		], $this->unsetColumns($values, ['createdAt']));
	}

	public function testFindByWithSelectJoins(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, orderBy: ['id' => 'ASC'], select: ['id', 'author.name'])->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'name' => 'John Doe',
			],
			[
				'id' => 2,
				'name' => 'Jane Smith',
			],
			[
				'id' => 3,
				'name' => 'John Doe',
			],
		], $this->unsetColumns($values, ['createdAt']));
	}

	public function testFindByWithOrderByJoins(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, orderBy: ['author.name' => 'ASC'], select: ['id', 'author.name'])->asArray();

		$this->assertSame([
			[
				'id' => 2,
				'name' => 'Jane Smith',
			],
			[
				'id' => 1,
				'name' => 'John Doe',
			],
			[
				'id' => 3,
				'name' => 'John Doe',
			],
		], $this->unsetColumns($values, ['createdAt']));
	}

	public function testFindByWithSubQuery(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$em = $this->createManagerRegistry()->getManager();

		assert($em instanceof EntityManagerInterface);

		$this->persistFixtures();
		$queries = $this->getQueries();

		$subQuery = $em->createQueryBuilder()
			->select('e.id')
			->from(Author::class, 'e')
			->where('e.name = :author')
			->setParameter('author', 'John Doe');

		$values = $queries->findBy(Article::class, [
			'author' => $subQuery->getQuery(),
		])->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
				'symbol' => 'sym',
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
		], $values);
	}

	public function testFindByWithSubQueryBuilder(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$em = $this->createManagerRegistry()->getManager();

		assert($em instanceof EntityManagerInterface);

		$this->persistFixtures();
		$queries = $this->getQueries();

		$subQuery = $em->createQueryBuilder()
			->select('e.id')
			->from(Author::class, 'e')
			->where('e.name = :author')
			->setParameter('author', 'John Doe');

		$values = $queries->findBy(Article::class, [
			'author' => $subQuery,
		])->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
				'symbol' => 'sym',
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
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
				'type' => 'news',
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
		], $values);
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
				'symbol' => 'sym',
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
			'Another Article' => [
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
			'Third Article' => [
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
		], $values);
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
				'symbol' => 'sym',
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
			'Another Article' => [
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
			'Third Article' => [
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
			],
		], $values);
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

	public function testFindByWithRelations(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, select: ['**'])->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
				'symbol' => 'sym',
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
				'author' => 1,
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
				'author' => 2,
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'symbol' => null,
				'createdAt' => '2021-01-01 00:00:00',
				'type' => 'news',
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

	public function testFindColumnValuesSelectRelation(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'author')->asArray();

		$this->assertSame([
			1,
			1,
			2,
		], $values);
	}

	public function testFindColumnValuesSelectDistinct(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'author', distinct: true)->asArray();

		$this->assertSame([
			1,
			2,
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

	public function testFindOneBy(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$value = $queries->findOneBy(Article::class, ['id' => 1]);

		$this->assertSame([
			'id' => 1,
			'title' => 'Sample Article',
			'content' => 'This is a sample article.',
			'symbol' => 'sym',
			'createdAt' => '2021-01-01 00:00:00',
			'type' => 'news',
		], $value);
	}

	public function testFindOneByReturnsNull(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$value = $queries->findOneBy(Article::class, ['id' => 999]);

		$this->assertNull($value);
	}

	private function getQueries(): ScalarQueries
	{
		return new ScalarQueries(new SimplifiedQueryBuilderFactory($this->createManagerRegistry()));
	}

}
