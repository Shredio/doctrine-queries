<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\DoctrineQueries;
use Tests\Entity\Article;
use Tests\Entity\TypedValues;

class AggregationResultArrayKeyRuleCases
{

	public DoctrineQueries $doctrineQueries;

	public function nullableKey(): void
	{
		$this->doctrineQueries->sumGroupedBy(Article::class, 'id', 'symbol')->asArray();
		$this->doctrineQueries->minGroupedBy(Article::class, 'id', 'author.role.name')->setLimit(10)->asArray();
		$this->doctrineQueries->maxGroupedBy(Article::class, criteria: ['id >' => 1], groupBy: 'author.role', field: 'id')->asArray();
	}

	public function keyThatIsNotAnArrayKey(): void
	{
		$this->doctrineQueries->sumGroupedBy(TypedValues::class, 'small', 'ratio')->asArray();
	}

	public function validKeys(): void
	{
		$this->doctrineQueries->sumGroupedBy(Article::class, 'id', 'author')->asArray();
		$this->doctrineQueries->minGroupedBy(Article::class, 'id', 'symbol', ['symbol !=' => null])->asArray();
		$this->doctrineQueries->maxGroupedBy(Article::class, 'id', 'author.role.name', joinConfig: 'inner')->asArray();
		$this->doctrineQueries->maxGroupedBy(TypedValues::class, 'nullableMoment', 'day')->asArray();
		$this->doctrineQueries->sumGroupedBy(Article::class, 'id', 'symbol')->yield();
		$this->doctrineQueries->sumBy(Article::class, 'id');
		$this->doctrineQueries->maxBy(Article::class, 'symbol');
	}

}
