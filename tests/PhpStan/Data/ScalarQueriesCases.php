<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;
use Tests\Entity\ArticleNullableAuthor;
use function PHPStan\Testing\assertType;

class ScalarQueriesCases
{

	public function testFindBy(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string}>', $queries->findBy(Article::class));
	}

	public function testFindOneBy(ScalarQueries $queries): void
	{
		assertType('array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string}|null', $queries->findOneBy(Article::class));
	}

	public function testFindIndexedBy(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseIndexedResults<string, array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string}>', $queries->findIndexedBy(Article::class, 'title'));
		assertType('Shredio\DoctrineQueries\Result\DatabaseIndexedResults<int, array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string}>', $queries->findIndexedBy(Article::class, 'id'));
		assertType('Shredio\DoctrineQueries\Result\DatabaseIndexedResults<\'Foo\', array{id: int, title: \'Foo\', content: string, symbol: string|null, createdAt: string, type: string}>', $queries->findIndexedBy(Article::class, 'title', criteria: ['title' => 'Foo']));
		assertType('Shredio\DoctrineQueries\Result\DatabaseIndexedResults<\'Bar\'|\'Foo\', array{id: int, title: \'Bar\'|\'Foo\', content: string, symbol: string|null, createdAt: string, type: string}>', $queries->findIndexedBy(Article::class, 'title', criteria: ['title' => ['Foo', 'Bar']]));
	}

	public function testFindByYield(ScalarQueries $queries): void
	{
		assertType('iterable<int, array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string}>', $queries->findBy(Article::class)->yield());
	}

	public function testFindByAsArray(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string}>', $queries->findBy(Article::class)->asArray());
	}

	public function testFindByWithCriteria(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: 1, title: string, content: string, symbol: string, createdAt: string, type: string}>', $queries->findBy(Article::class, ['id' => 1, 'symbol !=' => null]));
	}

	public function testFindByWithSelect(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string}>', $queries->findBy(Article::class, [], [], ['id', 'title']));
	}

	public function testFindByWithRelations(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string, author: int}>', $queries->findBy(Article::class, select: ['**']));
	}

	public function testFindByWithNullableRelations(ScalarQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string, author: int|null}>', $queries->findBy(ArticleNullableAuthor::class, select: ['**']));
	}

	public function testFindByWithRelationsYield(ScalarQueries $queries): void
	{
		assertType('iterable<int, array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string, author: int}>', $queries->findBy(Article::class, select: ['**'])->yield());
	}

	public function testFindByWithRelationsAsArray(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string, author: int}>', $queries->findBy(Article::class, select: ['**'])->asArray());
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
		assertType('list<int|null>', $queries->findColumnValuesBy(ArticleNullableAuthor::class, 'author')->asArray());
		assertType('list<int>', $queries->findColumnValuesBy(Article::class, 'author')->asArray());
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

	public function testFindByWithRelationSingleWildcard(ScalarQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array{id: int, name: string}>', $queries->findBy(Article::class, select: ['author.*']));
	}

	public function testFindByWithRelationSingleWildcardAsArray(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, name: string}>', $queries->findBy(Article::class, select: ['author.*'])->asArray());
	}

	public function testFindByWithRelationDoubleWildcard(ScalarQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array{id: int, name: string, role: int|null}>', $queries->findBy(Article::class, select: ['author.**']));
	}

	public function testFindByWithRelationDoubleWildcardAsArray(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, name: string, role: int|null}>', $queries->findBy(Article::class, select: ['author.**'])->asArray());
	}

	public function testFindByLeftJoinNonNullable(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, name: string, role: int|null}>', $queries->findBy(Article::class, select: ['author.**'], joinConfig: ['author' => 'left'])->asArray());
	}

	public function testFindByLeftJoin(ScalarQueries $queries): void
	{
		assertType('list<array{id: int|null, name: string|null, role: int|null}>', $queries->findBy(ArticleNullableAuthor::class, select: ['author.**'], joinConfig: ['author' => 'left'])->asArray());
	}

	public function testFindByInnerJoinNullable(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, name: string, role: int|null}>', $queries->findBy(ArticleNullableAuthor::class, select: ['author.**'], joinConfig: ['author' => 'inner'])->asArray());
	}

	public function testFindByLeftJoinNested(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, name: string, role_id: int|null, role_name: string|null}>', $queries->findBy(Article::class, select: ['author.*', 'author.role.*' => 'role_'], joinConfig: ['author.role' => 'left'])->asArray());
	}

	public function testRelationPrefixFindByRelationWithWildcardRelationSelection(ScalarQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string, author_id: int, author_name: string, author_role: int|null}>', $queries->findBy(Article::class, select: ['*', 'author.**' => 'author_']));
	}

	public function testRelationPrefixFindByRelationWithWildcardRelationSelectionAsArray(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string, author_id: int, author_name: string, author_role: int|null}>', $queries->findBy(Article::class, select: ['*', 'author.**' => 'author_'])->asArray());
	}

	public function testNullableRelationPrefixFindByRelationWithWildcardRelationSelectionAsArray(ScalarQueries $queries): void
	{
		assertType('list<array{id: int, title: string, content: string, symbol: string|null, createdAt: string, type: string, author_id: int|null, author_name: string|null, author_role: int|null}>', $queries->findBy(ArticleNullableAuthor::class, select: ['*', 'author.**' => 'author_'])->asArray());
	}

}
