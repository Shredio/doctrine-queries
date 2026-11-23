<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\DoctrineQueries;
use Tests\Entity\Article;
use Tests\Entity\ArticleNullableAuthor;
use Tests\Entity\TypedValues;

class CountGroupedByResultArrayKeyRuleCases
{

	public DoctrineQueries $doctrineQueries;

	public function nullableKey(): void
	{
		$this->doctrineQueries->countGroupedBy(Article::class, 'symbol')->asArray();
		$this->doctrineQueries->countGroupedBy(Article::class, 'author.role.name')->setLimit(10)->asArray();
		$this->doctrineQueries->countGroupedBy(ArticleNullableAuthor::class, criteria: ['id >' => 1], groupBy: 'author')->asArray();
		$this->doctrineQueries->countGroupedBy(Article::class, 'author.role', joinConfig: ['author' => 'inner'])->asArray();
	}

	public function keyThatIsNotAnArrayKey(): void
	{
		$this->doctrineQueries->countGroupedBy(TypedValues::class, 'ratio')->asArray();
	}

	public function validKeys(): void
	{
		$this->doctrineQueries->countGroupedBy(Article::class, 'author')->asArray();
		$this->doctrineQueries->countGroupedBy(Article::class, 'symbol', ['symbol !=' => null])->asArray();
		$this->doctrineQueries->countGroupedBy(Article::class, 'author.role.name', joinConfig: 'inner')->asArray();
		$this->doctrineQueries->countGroupedBy(Article::class, 'author.role', ['author.role !=' => null])->asArray();
		$this->doctrineQueries->countGroupedBy(Article::class, 'createdAt')->asArray();
		$this->doctrineQueries->countGroupedBy(Article::class, 'type')->asArray();
		$this->doctrineQueries->countGroupedBy(Article::class, 'symbol')->yield();
		$this->doctrineQueries->countBy(Article::class, field: 'symbol');
	}

	/**
	 * Reported by DoctrineQueriesRule.
	 */
	public function invalidCalls(string $groupBy): void
	{
		$this->doctrineQueries->countGroupedBy(Article::class, 'nonExistentKey')->asArray();
		$this->doctrineQueries->countGroupedBy(Article::class, $groupBy)->asArray();
		$this->doctrineQueries->countGroupedBy(UnrelatedFinder::class, 'symbol')->asArray();
	}

}
