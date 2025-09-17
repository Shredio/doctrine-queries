<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use Shredio\DoctrineQueries\DoctrineQueries;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\TestManagerRegistry;
use Tests\Entity\Article;
use Tests\TestCase;

final class DoctrineQueriesTest extends TestCase
{

	use DoctrineContext;

	public function testCreateQueryFromFileSuccess(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();
		
		$sqlFile = __DIR__ . '/test_query.sql';
		$rawQueryBuilder = $queries->createQueryFromFile(Article::class, $sqlFile);
		
		// Test that the query can be executed with parameters
		$results = $rawQueryBuilder->setParameter('articleId', 1)->getResult()->asArray();
		$this->assertIsArray($results);
		$this->assertCount(1, $results);
		$this->assertSame('Sample Article', $results[0]['title']);
		$this->assertSame('John Doe', $results[0]['author_name']);
	}

	public function testCreateQueryFromFileWithCountQuery(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();
		
		$sqlFile = __DIR__ . '/count_articles.sql';
		$rawQueryBuilder = $queries->createQueryFromFile(Article::class, $sqlFile);
		
		// Test count query with author parameter
		$results = $rawQueryBuilder->setParameter('authorId', 1)->getResult()->asArray();
		$this->assertIsArray($results);
		$this->assertCount(1, $results);
		$this->assertSame(2, (int) $results[0]['total_count']);
	}

	public function testCreateQueryFromFileWithInClause(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();
		
		$sqlFile = __DIR__ . '/test_in_query.sql';
		$rawQueryBuilder = $queries->createQueryFromFile(Article::class, $sqlFile);
		
		// Test IN clause with array of IDs
		$results = $rawQueryBuilder->setParameter('articleIds', [1, 3])->getResult()->asArray();
		$this->assertCount(2, $results);
		$this->assertSame('Sample Article', $results[0]['title']);
		$this->assertSame('Third Article', $results[1]['title']);
		$this->assertSame('John Doe', $results[0]['author_name']);
		$this->assertSame('John Doe', $results[1]['author_name']);
	}

	public function testSubQuery(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		// Create a sub-query to find articles by a specific author
		$authorId = 1; // Assuming this author exists in the fixtures
		$subQuery = $queries->subQuery(Article::class, ['author' => $authorId]);

		// Use the sub-query to find articles
		$results = $queries->objects->findBy(Article::class, ['author' => $subQuery])->asArray();

		$this->assertCount(2, $results); // Assuming the author has 2 articles in fixtures
	}

	public function testExistsManyByWithExistingEntities(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$valuesToCheck = [
			['id' => 1], // exists
			['id' => 2], // exists
			['id' => 3], // exists
		];

		$results = $queries->existsManyBy(Article::class, $valuesToCheck);

		$this->assertTrue($results->has(['id' => 1]));
		$this->assertTrue($results->has(['id' => 2]));
		$this->assertTrue($results->has(['id' => 3]));
	}

	public function testExistsManyByWithNonExistingEntities(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$valuesToCheck = [
			['id' => 999], // does not exist
			['id' => 1000], // does not exist
		];

		$results = $queries->existsManyBy(Article::class, $valuesToCheck);

		$this->assertFalse($results->has(['id' => 999]));
		$this->assertFalse($results->has(['id' => 1000]));
	}

	public function testExistsManyByWithMixedEntities(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$valuesToCheck = [
			['id' => 1], // exists
			['id' => 999], // does not exist
			['id' => 2], // exists
		];

		$results = $queries->existsManyBy(Article::class, $valuesToCheck);

		$this->assertTrue($results->has(['id' => 1]));
		$this->assertFalse($results->has(['id' => 999]));
		$this->assertTrue($results->has(['id' => 2]));
	}

	public function testExistsManyByWithMultipleCriteria(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$valuesToCheck = [
			['id' => 1, 'author' => 1], // exists
			['id' => 2, 'author' => 2], // exists
			['id' => 1, 'author' => 999], // does not exist (wrong author)
		];

		$results = $queries->existsManyBy(Article::class, $valuesToCheck);

		$this->assertTrue($results->has(['id' => 1, 'author' => 1]));
		$this->assertTrue($results->has(['id' => 2, 'author' => 2]));
		$this->assertFalse($results->has(['id' => 1, 'author' => 999]));
	}

	public function testExistsManyByWithEmptyValues(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$results = $queries->existsManyBy(Article::class, []);

		$this->assertFalse($results->has(['id' => 1]));
		$this->assertFalse($results->has(['id' => 999]));
	}

	private function getQueries(): DoctrineQueries
	{
		return new DoctrineQueries(new TestManagerRegistry($this->getEntityManager()));
	}

}
