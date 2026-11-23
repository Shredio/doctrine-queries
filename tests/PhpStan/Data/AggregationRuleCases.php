<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\DoctrineQueries;
use Tests\Entity\Article;
use Tests\Entity\TypedValues;

class AggregationRuleCases
{

	public DoctrineQueries $doctrineQueries;

	public function validCalls(): void
	{
		$this->doctrineQueries->sumBy(TypedValues::class, 'small', ['label' => 'a']);
		$this->doctrineQueries->sumBy(TypedValues::class, 'price');
		$this->doctrineQueries->sumBy(TypedValues::class, 'ratio', joinConfig: 'inner');
		$this->doctrineQueries->sumBy(Article::class, 'author.role.id', joinConfig: ['author.role' => 'inner']);
		$this->doctrineQueries->minBy(TypedValues::class, 'day');
		$this->doctrineQueries->maxBy(Article::class, 'symbol', ['hashTags.hashtag' => 'php']);
		$this->doctrineQueries->maxBy(Article::class, 'hashTags.hashtag');
		$this->doctrineQueries->sumGroupedBy(TypedValues::class, 'small', 'label', ['kind' => 'news']);
		$this->doctrineQueries->minGroupedBy(Article::class, 'createdAt', 'author', joinConfig: 'inner');
		$this->doctrineQueries->maxGroupedBy(Article::class, criteria: ['id >' => 1], groupBy: 'author.role', field: 'title');
	}

	public function invalidField(string $field): void
	{
		$this->doctrineQueries->sumBy(TypedValues::class, 'label');
		$this->doctrineQueries->sumBy(TypedValues::class, 'day');
		$this->doctrineQueries->sumGroupedBy(Article::class, 'title', 'author');
		$this->doctrineQueries->sumBy(Article::class, 'author');
		$this->doctrineQueries->minBy(Article::class, 'author');
		$this->doctrineQueries->maxGroupedBy(Article::class, 'author.role', 'author');
		$this->doctrineQueries->maxBy(Article::class, 'hashTags');
		$this->doctrineQueries->minBy(Article::class, 'nonExistentField');
		$this->doctrineQueries->sumBy(Article::class, 'auth.id');
		$this->doctrineQueries->maxBy(Article::class, '*');
		$this->doctrineQueries->sumBy(TypedValues::class, $field);
	}

	public function invalidOtherArguments(string $groupBy): void
	{
		$this->doctrineQueries->sumGroupedBy(Article::class, 'id', 'nonExistentKey');
		$this->doctrineQueries->minGroupedBy(Article::class, 'id', 'author.*');
		$this->doctrineQueries->maxGroupedBy(Article::class, 'id', 'hashTags');
		$this->doctrineQueries->maxGroupedBy(Article::class, 'id', $groupBy);
		$this->doctrineQueries->sumBy(Article::class, 'id', ['nonExistentCriteria' => 1]);
		$this->doctrineQueries->maxBy(Article::class, 'id', [], 'inner');
		$this->doctrineQueries->sumGroupedBy(Article::class, 'id', 'author', [], ['author' => 'inner']);
		$this->doctrineQueries->minBy(Article::class, 'id', joinConfig: ['auth' => 'inner']);
		$this->doctrineQueries->sumBy('NonExistentEntity', 'id');
	}

	public function customTypes(): void // the class is not imported, an import would move the lines of the cases above
	{
		$this->doctrineQueries->sumBy(\Tests\Entity\UsageRecord::class, 'cost');
		$this->doctrineQueries->sumGroupedBy(\Tests\Entity\UsageRecord::class, 'discount', 'provider');
		$this->doctrineQueries->sumBy(\Tests\Entity\UsageRecord::class, 'previous.cost');
		$this->doctrineQueries->maxBy(\Tests\Entity\UsageRecord::class, 'account');
		$this->doctrineQueries->sumBy(\Tests\Entity\UsageRecord::class, 'account');
		$this->doctrineQueries->sumGroupedBy(\Tests\Entity\UsageRecord::class, 'previous.account', 'provider');
	}

}
