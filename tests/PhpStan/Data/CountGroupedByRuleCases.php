<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\DoctrineQueries;
use Tests\Entity\Article;

class CountGroupedByRuleCases
{

	public DoctrineQueries $doctrineQueries;

	public function validCalls(): void
	{
		$this->doctrineQueries->countGroupedBy(Article::class, 'author', ['id' => [1, 2]]);
		$this->doctrineQueries->countGroupedBy(Article::class, 'author.name', field: 'symbol', distinct: true, joinConfig: 'inner');
		$this->doctrineQueries->countGroupedBy(Article::class, 'hashTags.hashtag', ['hashTags.hashtag' => 'php'], field: null);
		$this->doctrineQueries->countGroupedBy(Article::class, criteria: ['author.role.name' => 'Administrator'], groupBy: 'author.role', joinConfig: ['author.role' => 'inner']);
	}

	public function invalidGroupBy(string $groupBy): void
	{
		$this->doctrineQueries->countGroupedBy(Article::class, 'nonExistentKey');
		$this->doctrineQueries->countGroupedBy(Article::class, 'auth.name');
		$this->doctrineQueries->countGroupedBy(Article::class, 'author.*');
		$this->doctrineQueries->countGroupedBy(Article::class, 'hashTags');
		$this->doctrineQueries->countGroupedBy(Article::class, $groupBy);
		$this->doctrineQueries->countGroupedBy(Article::class, field: 'id', groupBy: 'nonExistentNamedKey');
	}

	public function invalidOtherArguments(): void
	{
		$this->doctrineQueries->countGroupedBy(Article::class, 'author', ['nonExistentField' => 1]);
		$this->doctrineQueries->countGroupedBy(Article::class, 'author', ['id IN (1) OR e0.id =' => 1]);
		$this->doctrineQueries->countGroupedBy(Article::class, 'author', field: 'nonExistentCountedField');
		$this->doctrineQueries->countGroupedBy(Article::class, 'author', field: '*');
		$this->doctrineQueries->countGroupedBy(Article::class, 'author', [], null, false, 'inner');
		$this->doctrineQueries->countGroupedBy(Article::class, 'author', joinConfig: ['auth' => 'inner']);
		$this->doctrineQueries->countGroupedBy('NonExistentEntity', 'author');
	}

}
