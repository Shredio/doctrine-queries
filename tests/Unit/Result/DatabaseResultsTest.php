<?php declare(strict_types = 1);

namespace Tests\Unit\Result;

use Doctrine\ORM\Query;
use Shredio\DoctrineQueries\Result\DatabaseResults;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\TestCase;

final class DatabaseResultsTest extends TestCase
{

	use DoctrineContext;

	public function testYieldPassesBatchesToFetchCallbackBeforeReturningThem(): void
	{
		$this->persistFixtures();
		$events = [];

		$results = new DatabaseResults(
			$this->createArticleQuery(),
			static function (array $articles) use (&$events): void {
				$events[] = sprintf('fetch %s', implode(',', array_map(static fn (Article $article): int => $article->getId(), $articles)));
			},
			fetchBatchSize: 2,
		);

		$keys = [];
		foreach ($results->yield() as $key => $article) {
			$keys[] = $key;
			$events[] = sprintf('yield %d', $article->getId());
		}

		$this->assertSame(['fetch 1,2', 'yield 1', 'yield 2', 'fetch 3', 'yield 3'], $events);
		$this->assertSame([0, 1, 2], $keys, 'The keys continue across the batches.');
	}

	public function testYieldOfFullBatchesDoesNotCallFetchCallbackWithoutResults(): void
	{
		$this->persistFixtures();
		$batchSizes = [];

		$results = new DatabaseResults(
			$this->createArticleQuery(),
			static function (array $articles) use (&$batchSizes): void {
				$batchSizes[] = count($articles);
			},
			fetchBatchSize: 3,
		);

		$this->assertCount(3, iterator_to_array($results->yield(), false));
		$this->assertSame([3], $batchSizes);
	}

	public function testYieldOfFewerResultsThanBatchIsSingleBatch(): void
	{
		$this->persistFixtures();
		$batchSizes = [];

		$results = new DatabaseResults(
			$this->createArticleQuery(),
			static function (array $articles) use (&$batchSizes): void {
				$batchSizes[] = count($articles);
			},
		);

		$this->assertCount(3, iterator_to_array($results->yield(), false));
		$this->assertSame([3], $batchSizes);
	}

	/**
	 * @return Query<int, Article>
	 */
	private function createArticleQuery(): Query
	{
		/** @var Query<int, Article> */
		return $this->getEntityManager()->createQuery(sprintf('SELECT a FROM %s a ORDER BY a.id ASC', Article::class));
	}

}
