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

	private function getQueries(): DoctrineQueries
	{
		return new DoctrineQueries(new TestManagerRegistry($this->getEntityManager()));
	}

}
