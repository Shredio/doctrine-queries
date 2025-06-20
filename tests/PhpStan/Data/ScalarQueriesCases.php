<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;
use function PHPStan\Testing\assertType;

class ScalarQueriesCases
{

	public function testFindBy(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string, content: string, symbol: string|null, createdAt: string}>', $queries->findBy(Article::class));
	}

	public function testFindByYield(ScalarQueries $queries): void
	{
		assertType('iterable<int, array{id: int, title: string, content: string, symbol: string|null, createdAt: string}>', $queries->findBy(Article::class)->yield());
	}

	public function testFindByAsArray(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, title: string, content: string, symbol: string|null, createdAt: string}>', $queries->findBy(Article::class)->asArray());
	}

	public function testFindByWithCriteria(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: 1, title: string, content: string, symbol: string, createdAt: string}>', $queries->findBy(Article::class, ['id' => 1, 'symbol !=' => null]));
	}

	public function testFindByWithSelect(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string}>', $queries->findBy(Article::class, [], [], ['id', 'title']));
	}

	public function testFindByWithRelations(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, author: int}>', $queries->findByWithRelations(Article::class));
	}

	public function testFindByWithRelationsYield(ScalarQueries $queries): void
	{
		assertType('iterable<int, array{id: int, title: string, content: string, symbol: string|null, createdAt: string, author: int}>', $queries->findByWithRelations(Article::class)->yield());
	}

	public function testFindByWithRelationsAsArray(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, author: int}>', $queries->findByWithRelations(Article::class)->asArray());
	}

	public function testFindPairsBy(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabasePairs<int, string>', $queries->findPairsBy(Article::class, 'id', 'title'));
		assertType('Shredio\DoctrineQueries\Result\DatabasePairs<int, string|null>', $queries->findPairsBy(Article::class, 'id', 'symbol'));
	}

	public function testFindPairsByYield(ScalarQueries $queries): void
	{
		assertType('iterable<int, string>', $queries->findPairsBy(Article::class, 'id', 'title')->yield());
		assertType('iterable<int, string|null>', $queries->findPairsBy(Article::class, 'id', 'symbol')->yield());
	}

	public function testFindPairsByAsArray(ScalarQueries $queries): void
	{
		assertType('array<int, string>', $queries->findPairsBy(Article::class, 'id', 'title')->asArray());
		assertType('array<int, string|null>', $queries->findPairsBy(Article::class, 'id', 'symbol')->asArray());
	}

	public function testFindColumnValuesBy(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseColumnValues<string>', $queries->findColumnValuesBy(Article::class, 'title'));
		assertType('Shredio\DoctrineQueries\Result\DatabaseColumnValues<int>', $queries->findColumnValuesBy(Article::class, 'id'));
		assertType('Shredio\DoctrineQueries\Result\DatabaseColumnValues<string|null>', $queries->findColumnValuesBy(Article::class, 'symbol'));
	}

	public function testFindColumnValuesByYield(ScalarQueries $queries): void
	{
		assertType('iterable<int, string>', $queries->findColumnValuesBy(Article::class, 'title')->yield());
		assertType('iterable<int, int>', $queries->findColumnValuesBy(Article::class, 'id')->yield());
		assertType('iterable<int, string|null>', $queries->findColumnValuesBy(Article::class, 'symbol')->yield());
	}

	public function testFindColumnValuesByAsArray(ScalarQueries $queries): void
	{
		assertType('list<string>', $queries->findColumnValuesBy(Article::class, 'title')->asArray());
		assertType('list<int>', $queries->findColumnValuesBy(Article::class, 'id')->asArray());
		assertType('list<string|null>', $queries->findColumnValuesBy(Article::class, 'symbol')->asArray());
	}

	public function testFindSingleColumnValueBy(ScalarQueries $queries): void
	{
		assertType('string|null', $queries->findSingleColumnValueBy(Article::class, 'title', []));
		assertType('int|null', $queries->findSingleColumnValueBy(Article::class, 'id', []));
		assertType('string|null', $queries->findSingleColumnValueBy(Article::class, 'symbol', ['symbol !=' => null]));
	}

	public function testFindSingleColumnValueByWithCriteria(ScalarQueries $queries): void
	{
		assertType('string|null', $queries->findSingleColumnValueBy(Article::class, 'title', ['id' => 1]));
	}

	/**
	 * @param list<string> $symbols
	 * @param list<string|null> $nullableSymbols
	 */
	public function testInCriteriaTypes(ScalarQueries $queries, array $symbols = [], array $nullableSymbols = []): void
	{
		assertType("list<array{symbol: 'A'|'B'}>", $queries->findBy(Article::class, ['symbol' => ['A', 'B']], select: ['symbol'])->asArray());
		assertType("list<array{symbol: string}>", $queries->findBy(Article::class, ['symbol' => $symbols], select: ['symbol'])->asArray());
		assertType("list<array{symbol: string|null}>", $queries->findBy(Article::class, ['symbol' => $nullableSymbols], select: ['symbol'])->asArray());
		assertType("list<array{symbol: 'A'|null}>", $queries->findBy(Article::class, ['symbol' => ['A', null]], select: ['symbol'])->asArray());
	}

}
