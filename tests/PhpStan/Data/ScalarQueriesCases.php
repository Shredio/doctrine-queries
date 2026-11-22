<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ScalarQueries;
use SortDirection;
use Tests\Entity\Article;
use Tests\Entity\ArticleNullableAuthor;
use Tests\Entity\FavouritePrompt;
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
		assertType('string|null', $queries->findSingleColumnValueBy(Article::class, 'author.role.name', [], orderBy: ['id' => SortDirection::Descending], joinConfig: 'inner'));
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

	public function testFindColumnValuesByCompositeKey(ScalarQueries $queries): void
	{
		assertType('list<int>', $queries->findColumnValuesBy(FavouritePrompt::class, 'prompt', ['account' => 1])->asArray());
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseColumnValues<int>', $queries->findColumnValuesBy(FavouritePrompt::class, 'prompt', ['account' => 1]));
	}

	public function testCriteriaNarrowOnlyTheFieldWithTheSamePath(ScalarQueries $queries): void
	{
		assertType('list<array{id: 1, authorId: int}>', $queries->findBy(Article::class, ['id' => 1], select: ['id', 'author.id' => 'authorId'])->asArray());
		assertType('list<array{id: int, authorId: 5}>', $queries->findBy(Article::class, ['author.id' => 5], select: ['id', 'author.id' => 'authorId'])->asArray());
		assertType('list<array{id: 1, author_id: int, author_name: string}>', $queries->findBy(Article::class, ['id' => 1], select: ['id', 'author.*' => 'author_'])->asArray());
	}

	public function testCriteriaValuesIncompatibleWithTheFieldType(ScalarQueries $queries): void
	{
		assertType('list<array{id: int}>', $queries->findBy(Article::class, ['id' => '2'], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $queries->findBy(Article::class, ['id' => [1, '2']], select: ['id'])->asArray());
		assertType('list<array{id: 1|2}>', $queries->findBy(Article::class, ['id' => [1, 2]], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $queries->findBy(Article::class, ['id' => []], select: ['id'])->asArray());
	}

	public function testCriteriaOperatorsAreNormalized(ScalarQueries $queries): void
	{
		assertType('list<array{symbol: string}>', $queries->findBy(Article::class, ['symbol <>' => null], select: ['symbol'])->asArray());
		assertType("list<array{title: 'Foo'}>", $queries->findBy(Article::class, ['title  =' => 'Foo'], select: ['title'])->asArray());
		assertType('list<array{id: int}>', $queries->findBy(Article::class, ['id BETWEEN' => 1], select: ['id'])->asArray());
	}

	public function testFindIndexedByAssociation(ScalarQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseIndexedResults<int, array{title: string}>', $queries->findIndexedBy(Article::class, 'author', select: ['title']));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseIndexedResults<string, array{id: int}>', $queries->findIndexedBy(Article::class, 'author.name', select: ['id']));
	}

	/**
	 * Invalid arguments are reported by DoctrineQueriesRule, the type extension must not crash on them.
	 */
	public function testInvalidArgumentsDoNotBreakTheAnalysis(ScalarQueries $queries): void
	{
		assertType('list<array{id: int}>', $queries->findBy(Article::class, select: ['id', 'unknown', 'author.rol.name', 'title.length'])->asArray());
		assertType('mixed', $queries->findSingleColumnValueBy(Article::class, 'unknown', []));
		assertType('list', $queries->findColumnValuesBy(Article::class, 'author.rol.name')->asArray());
		assertType('list', $queries->findColumnValuesBy(Article::class, 'hashTags')->asArray());
		assertType('array<int|string, mixed>', $queries->findPairsBy(Article::class, 'unknownKey', 'unknownValue')->asArray());
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array<string, bool|float|int|string|null>>', $queries->findBy(\stdClass::class));
	}


	/**
	 * A field of a left joined relation is NULL in the rows without the relation, in every finder.
	 */
	public function testLeftJoinedFieldsAreNullable(ScalarQueries $queries): void
	{
		assertType('list<int|null>', $queries->findColumnValuesBy(Article::class, 'author.role.id')->asArray());
		assertType('list<int>', $queries->findColumnValuesBy(Article::class, 'author.role.id', joinConfig: 'inner')->asArray());
		assertType('list<int>', $queries->findColumnValuesBy(Article::class, 'author.role.id', joinConfig: ['author.role' => 'inner'])->asArray());
		assertType('list<int>', $queries->findColumnValuesBy(Article::class, 'author.role.id', ['author.role.id !=' => null])->asArray());
		assertType('list<string>', $queries->findColumnValuesBy(Article::class, 'author.name')->asArray());
		assertType('list<string|null>', $queries->findColumnValuesBy(ArticleNullableAuthor::class, 'author.name')->asArray());
		assertType('array<int, string|null>', $queries->findPairsBy(Article::class, 'id', 'author.role.name')->asArray());
		assertType('array<int, string>', $queries->findPairsBy(Article::class, 'id', 'author.role.name', joinConfig: 'inner')->asArray());
		assertType('string|null', $queries->findSingleColumnValueBy(Article::class, 'author.role.name', []));
	}

	/**
	 * asArray() throws on a NULL key, which ResultArrayKeyRule reports, so the keys never contain it.
	 */
	public function testKeysAreNeverNull(ScalarQueries $queries): void
	{
		assertType('array<string, int>', $queries->findPairsBy(Article::class, 'symbol', 'id')->asArray());
		assertType('array<int, int>', $queries->findPairsBy(Article::class, 'author.role.id', 'id')->asArray());
		assertType('array<string, array{id: int}>', $queries->findIndexedBy(Article::class, 'symbol', select: ['id'])->asArray());
		assertType('array<int, array{id: int}>', $queries->findIndexedBy(Article::class, 'author.role.id', select: ['id'])->asArray());
	}

}
