<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
use Shredio\DoctrineQueries\Exception\InvalidResultKeyException;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\TestCase;
use Tests\Unit\Helpers;

/**
 * Hydration specific to ArrayQueries, the shared behaviour is in ArrayAndScalarQueriesTest.
 */
final class ArrayQueriesTest extends TestCase
{

	use DoctrineContext;
	use Helpers;

	private const string CreatedAt = 'DateTimeImmutable(2021-01-01 00:00:00)';
	private const string News = 'Tests\Entity\Enum\ArticleType::News';

	public function testFindByHydratesAllRootFields(): void
	{
		$this->persistFixtures();

		$values = $this->assertSingleQuery(fn (): array => $this->getQueries()->findBy(Article::class)->asArray());

		$this->assertSame([
			['id' => 1, 'title' => 'Sample Article', 'content' => 'This is a sample article.', 'symbol' => 'Symbol(sym)', 'createdAt' => self::CreatedAt, 'type' => self::News],
			['id' => 2, 'title' => 'Another Article', 'content' => 'This is another article.', 'symbol' => null, 'createdAt' => self::CreatedAt, 'type' => self::News],
			['id' => 3, 'title' => 'Third Article', 'content' => 'This is the third article.', 'symbol' => null, 'createdAt' => self::CreatedAt, 'type' => self::News],
		], self::describeObjects($values));
	}

	public function testFindByWildcardSelectsTheSameAsTheDefaultSelection(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$this->assertSame(
			self::describeObjects($queries->findBy(Article::class)->asArray()),
			self::describeObjects($queries->findBy(Article::class, select: ['*'])->asArray()),
		);
	}

	public function testFindByWithRelations(): void
	{
		$this->persistFixtures();

		$values = $this->assertSingleQuery(fn (): array => $this->getQueries()->findBy(Article::class, select: ['**'])->asArray());

		$this->assertSame([
			['id' => 1, 'title' => 'Sample Article', 'content' => 'This is a sample article.', 'symbol' => 'Symbol(sym)', 'createdAt' => self::CreatedAt, 'type' => self::News, 'author' => 1],
			['id' => 2, 'title' => 'Another Article', 'content' => 'This is another article.', 'symbol' => null, 'createdAt' => self::CreatedAt, 'type' => self::News, 'author' => 2],
			['id' => 3, 'title' => 'Third Article', 'content' => 'This is the third article.', 'symbol' => null, 'createdAt' => self::CreatedAt, 'type' => self::News, 'author' => 1],
		], self::describeObjects($values));
	}

	public function testFindByWithRelationPrefix(): void
	{
		$this->persistFixtures();

		$values = $this->assertSingleQuery(fn (): array => $this->getQueries()->findBy(Article::class, orderBy: ['id' => SortDirection::Ascending], select: ['*', 'author.**' => 'author_'])->asArray());

		$this->assertSame([
			['id' => 1, 'title' => 'Sample Article', 'content' => 'This is a sample article.', 'symbol' => 'Symbol(sym)', 'createdAt' => self::CreatedAt, 'type' => self::News, 'author_id' => 1, 'author_name' => 'John Doe', 'author_role' => null],
			['id' => 2, 'title' => 'Another Article', 'content' => 'This is another article.', 'symbol' => null, 'createdAt' => self::CreatedAt, 'type' => self::News, 'author_id' => 2, 'author_name' => 'Jane Smith', 'author_role' => 1],
			['id' => 3, 'title' => 'Third Article', 'content' => 'This is the third article.', 'symbol' => null, 'createdAt' => self::CreatedAt, 'type' => self::News, 'author_id' => 1, 'author_name' => 'John Doe', 'author_role' => null],
		], self::describeObjects($values));
	}

	public function testFindOneBy(): void
	{
		$this->persistFixtures();

		$value = $this->assertSingleQuery(fn (): ?array => $this->getQueries()->findOneBy(Article::class, ['id' => 1]));

		$this->assertSame(
			['id' => 1, 'title' => 'Sample Article', 'content' => 'This is a sample article.', 'symbol' => 'Symbol(sym)', 'createdAt' => self::CreatedAt, 'type' => self::News],
			self::describeObjects($value),
		);
	}

	public function testFindColumnValuesByHydratesObjects(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$this->assertSame(['Symbol(sym)', null, null], self::describeObjects($queries->findColumnValuesBy(Article::class, 'symbol')->asArray()));
		$this->assertSame([self::CreatedAt], self::describeObjects($queries->findColumnValuesBy(Article::class, 'createdAt', distinct: true)->asArray()));
		$this->assertSame([self::News], self::describeObjects($queries->findColumnValuesBy(Article::class, 'type', distinct: true)->asArray()));
		$this->assertSame('Symbol(sym)', self::describeObjects($queries->findSingleColumnValueBy(Article::class, 'symbol', ['id' => 1])));
	}

	public function testFindPairsByHydratesObjects(): void
	{
		$this->persistFixtures();

		$this->assertSame(
			[1 => 'Symbol(sym)', 2 => null, 3 => null],
			self::describeObjects($this->getQueries()->findPairsBy(Article::class, 'id', 'symbol')->asArray()),
		);
	}

	public function testFindIndexedByObjectCannotBeAnArrayKey(): void
	{
		$this->persistFixtures();
		$results = $this->getQueries()->findIndexedBy(Article::class, 'createdAt', select: ['id']);

		$keys = [];
		foreach ($results->yield() as $key => $row) {
			$keys[] = $key;
		}

		$this->assertCount(3, $keys);
		$this->assertContainsOnlyInstancesOf(DateTimeImmutable::class, $keys, 'yield() can return object keys.');

		$this->expectException(InvalidResultKeyException::class);
		$this->expectExceptionMessage('The key "createdAt" must be a string or an integer to be used as an array key, got DateTimeImmutable. Use ScalarQueries to get the database value, or iterate over yield().');

		$results->asArray();
	}

	public function testFindPairsByObjectCannotBeAnArrayKey(): void
	{
		$this->persistFixtures();
		$results = $this->getQueries()->findPairsBy(Article::class, 'type', 'id', orderBy: ['id' => SortDirection::Ascending]);

		$pairs = [];
		foreach ($results->yield() as $key => $value) {
			$pairs[] = [self::describeObjects($key), $value];
		}

		$this->assertSame([[self::News, 1], [self::News, 2], [self::News, 3]], $pairs, 'yield() can return object keys.');

		$this->expectException(InvalidResultKeyException::class);
		$this->expectExceptionMessage('The key "type" must be a string or an integer to be used as an array key, got Tests\Entity\Enum\ArticleType. Use ScalarQueries to get the database value, or iterate over yield().');

		$results->asArray();
	}

	private function getQueries(): ArrayQueries
	{
		return new ArrayQueries($this->createQueryBuilderFactory());
	}

}
