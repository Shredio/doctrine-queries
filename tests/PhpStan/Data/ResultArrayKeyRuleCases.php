<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;
use Tests\Entity\ArticleNullableAuthor;

class ResultArrayKeyRuleCases
{

	public ScalarQueries $scalarQueries;
	public ArrayQueries $arrayQueries;

	public function nullableKey(): void
	{
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id')->asArray();
		$this->scalarQueries->findIndexedBy(Article::class, 'symbol', select: ['id'])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'author.role.id', 'id')->asArray();
		$this->scalarQueries->findIndexedBy(Article::class, 'author.role.id', select: ['id'])->setLimit(10)->setOffset(5)->asArray();
		$this->scalarQueries->findPairsBy(Article::class, value: 'id', key: 'symbol')->asArray();
		$this->arrayQueries->findPairsBy(ArticleNullableAuthor::class, 'author', 'id')->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['id >' => 1], joinConfig: 'inner')->asArray();
	}

	public function keyThatIsNotAnArrayKey(): void
	{
		$this->arrayQueries->findPairsBy(Article::class, 'type', 'id')->asArray();
		$this->arrayQueries->findIndexedBy(Article::class, 'createdAt', select: ['id'])->asArray();
		$this->arrayQueries->findPairsBy(Article::class, 'symbol', 'id')->asArray();
	}

	public function validKeys(): void
	{
		$this->scalarQueries->findPairsBy(Article::class, 'id', 'symbol')->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['symbol !=' => null])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['symbol' => 'sym'])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'author.role.id', 'id', joinConfig: 'inner')->asArray();
		$this->scalarQueries->findIndexedBy(Article::class, 'author.role.id', select: ['id'], joinConfig: ['author.role' => 'inner'])->asArray();
		$this->scalarQueries->findIndexedBy(Article::class, 'author.role.id', ['author.role.id !=' => null], select: ['id'])->asArray();
		$this->scalarQueries->findIndexedBy(Article::class, 'author.name', select: ['id'])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'type', 'id')->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'createdAt', 'id')->asArray();
		$this->arrayQueries->findPairsBy(Article::class, 'author', 'id')->asArray();
		$this->arrayQueries->findIndexedBy(Article::class, 'title')->asArray();
	}

	public function yieldAcceptsAnyKey(): void
	{
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id')->yield();
		$this->arrayQueries->findIndexedBy(Article::class, 'createdAt', select: ['id'])->yield();
	}

	public function reportedByDoctrineQueriesRule(string $field): void
	{
		$this->scalarQueries->findPairsBy(Article::class, 'unknown', 'id')->asArray();
		$this->scalarQueries->findPairsBy(Article::class, '*', 'id')->asArray();
		$this->scalarQueries->findPairsBy(Article::class, $field, 'id')->asArray();
		$this->scalarQueries->findPairsBy('NonExistentEntity', 'id', 'id')->asArray();
	}

	public function otherResults(): void
	{
		$this->scalarQueries->findBy(Article::class)->asArray();
		$this->scalarQueries->findColumnValuesBy(Article::class, 'symbol')->asArray();
	}

	/**
	 * Only calls chained on the finder are checked.
	 */
	public function resultInVariableIsNotFollowed(): void
	{
		$pairs = $this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id');
		$pairs->asArray();
	}

	/**
	 * Calls that are not checked, the queries would be reported with the same arguments.
	 */
	public function uncheckedCalls(mixed $unknown, UnrelatedFinder $unrelatedFinder, string $method): void
	{
		$unknown->findPairsBy(Article::class, 'symbol', 'id')->asArray();
		$unrelatedFinder->findPairsBy(Article::class, 'symbol', 'id')->asArray();
		$this->scalarQueries->$method(Article::class, 'symbol', 'id')->asArray();
		$this->scalarQueries->findPairsBy(Article::class)->asArray();
	}

	public function entityReportedByDoctrineQueriesRule(): void
	{
		$this->scalarQueries->findPairsBy(UnrelatedFinder::class, 'symbol', 'id')->asArray();
	}

}
