<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use Shredio\DoctrineQueries\Query\ScalarQueries;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\TestCase;

/**
 * Hydration specific to ScalarQueries, the shared behaviour is in ArrayAndScalarQueriesTest.
 */
final class ScalarQueriesTest extends TestCase
{

	use DoctrineContext;

	public function testFindByReturnsDatabaseValues(): void
	{
		$this->persistFixtures();

		$values = $this->assertSingleQuery(fn (): array => $this->getQueries()->findBy(Article::class)->asArray());

		$this->assertSame([
			['id' => 1, 'title' => 'Sample Article', 'content' => 'This is a sample article.', 'symbol' => 'sym', 'createdAt' => self::FixtureCreatedAt, 'type' => 'news'],
			['id' => 2, 'title' => 'Another Article', 'content' => 'This is another article.', 'symbol' => null, 'createdAt' => self::FixtureCreatedAt, 'type' => 'news'],
			['id' => 3, 'title' => 'Third Article', 'content' => 'This is the third article.', 'symbol' => null, 'createdAt' => self::FixtureCreatedAt, 'type' => 'news'],
		], $values);
	}

	public function testFindByWildcardSelectsTheSameAsTheDefaultSelection(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$this->assertSame($queries->findBy(Article::class)->asArray(), $queries->findBy(Article::class, select: ['*'])->asArray());
	}

	public function testFindByWithRelations(): void
	{
		$this->persistFixtures();

		$values = $this->assertSingleQuery(fn (): array => $this->getQueries()->findBy(Article::class, select: ['**'])->asArray());

		$this->assertSame([
			['id' => 1, 'title' => 'Sample Article', 'content' => 'This is a sample article.', 'symbol' => 'sym', 'createdAt' => self::FixtureCreatedAt, 'type' => 'news', 'author' => 1],
			['id' => 2, 'title' => 'Another Article', 'content' => 'This is another article.', 'symbol' => null, 'createdAt' => self::FixtureCreatedAt, 'type' => 'news', 'author' => 2],
			['id' => 3, 'title' => 'Third Article', 'content' => 'This is the third article.', 'symbol' => null, 'createdAt' => self::FixtureCreatedAt, 'type' => 'news', 'author' => 1],
		], $values);
	}

	public function testFindByWithRelationPrefix(): void
	{
		$this->persistFixtures();

		$values = $this->assertSingleQuery(fn (): array => $this->getQueries()->findBy(Article::class, orderBy: ['id' => SortDirection::Ascending], select: ['*', 'author.**' => 'author_'])->asArray());

		$this->assertSame([
			['id' => 1, 'title' => 'Sample Article', 'content' => 'This is a sample article.', 'symbol' => 'sym', 'createdAt' => self::FixtureCreatedAt, 'type' => 'news', 'author_id' => 1, 'author_name' => 'John Doe', 'author_role' => null],
			['id' => 2, 'title' => 'Another Article', 'content' => 'This is another article.', 'symbol' => null, 'createdAt' => self::FixtureCreatedAt, 'type' => 'news', 'author_id' => 2, 'author_name' => 'Jane Smith', 'author_role' => 1],
			['id' => 3, 'title' => 'Third Article', 'content' => 'This is the third article.', 'symbol' => null, 'createdAt' => self::FixtureCreatedAt, 'type' => 'news', 'author_id' => 1, 'author_name' => 'John Doe', 'author_role' => null],
		], $values);
	}

	public function testFindOneBy(): void
	{
		$this->persistFixtures();

		$this->assertSame(
			['id' => 1, 'title' => 'Sample Article', 'content' => 'This is a sample article.', 'symbol' => 'sym', 'createdAt' => self::FixtureCreatedAt, 'type' => 'news'],
			$this->assertSingleQuery(fn (): ?array => $this->getQueries()->findOneBy(Article::class, ['id' => 1])),
		);
	}

	public function testFindColumnValuesByReturnsDatabaseValues(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$this->assertSame(['sym', null, null], $queries->findColumnValuesBy(Article::class, 'symbol')->asArray());
		$this->assertSame([self::FixtureCreatedAt], $queries->findColumnValuesBy(Article::class, 'createdAt', distinct: true)->asArray());
		$this->assertSame(['news'], $queries->findColumnValuesBy(Article::class, 'type', distinct: true)->asArray());
		$this->assertSame('sym', $queries->findSingleColumnValueBy(Article::class, 'symbol', ['id' => 1]));
	}

	public function testFindIndexedByDatabaseValueOfAnObjectField(): void
	{
		$this->persistFixtures();

		$this->assertSame(
			[self::FixtureCreatedAt => ['id' => 3]],
			$this->getQueries()->findIndexedBy(Article::class, 'createdAt', orderBy: ['id' => SortDirection::Ascending], select: ['id'])->asArray(),
		);
	}

	public function testFindPairsByDatabaseValueOfAnObjectField(): void
	{
		$this->persistFixtures();

		$this->assertSame(
			['news' => 3],
			$this->getQueries()->findPairsBy(Article::class, 'type', 'id', orderBy: ['id' => SortDirection::Ascending])->asArray(),
			'A later row with the same key overwrites the earlier one.',
		);
	}

	private function getQueries(): ScalarQueries
	{
		return new ScalarQueries($this->createQueryBuilderFactory());
	}

}
