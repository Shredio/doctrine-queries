<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;
use Tests\Entity\ArticleNullableAuthor;
use Tests\Entity\Author;
use function PHPStan\Testing\assertType;

/**
 * Arguments that are not a single known value: the result has the type of any of the queries that can run,
 * or the declared type when they are not known.
 */
class UncertainArgumentsCases
{

	/**
	 * @param list<string> $fields
	 * @param array{0: 'id', 1?: 'title'} $selectWithOptionalField
	 * @param array{0?: 'id', 1?: 'title', 2?: 'content', 3?: 'symbol', 4?: 'type'} $selectWithManyOptionalFields
	 * @param 'id'|'title' $idOrTitle
	 * @param 'first'|'second' $alias
	 */
	public function testSelect(
		ScalarQueries $scalars,
		bool $condition,
		array $fields,
		array $selectWithOptionalField,
		array $selectWithManyOptionalFields,
		string $idOrTitle,
		string $alias,
		string $field,
	): void
	{
		assertType('list<array{id: int}|array{title: string}>', $scalars->findBy(Article::class, select: $condition ? ['id'] : ['title'])->asArray());
		assertType('list<array{id: int, title?: string}>', $scalars->findBy(Article::class, select: $selectWithOptionalField)->asArray());
		assertType('list<array{id: int}|array{title: string}>', $scalars->findBy(Article::class, select: [$idOrTitle])->asArray());
		assertType('list<array{first: int}|array{second: int}>', $scalars->findBy(Article::class, select: ['id' => $alias])->asArray());
		assertType('array{id: int}|array{title: string}|null', $scalars->findOneBy(Article::class, select: $condition ? ['id'] : ['title']));
		assertType('array<int, array{id: int}|array{title: string}>', $scalars->findIndexedBy(Article::class, 'id', select: $condition ? ['id'] : ['title'])->asArray());
		// the criteria narrow the fields of each selection
		assertType("list<array{id: 1}|array{title: string}>", $scalars->findBy(Article::class, ['id' => 1], select: $condition ? ['id'] : ['title'])->asArray());

		$select = ['id'];
		if ($condition) {
			$select[] = 'title';
		}
		assertType('list<array{id: int, title?: string}>', $scalars->findBy(Article::class, select: $select)->asArray());

		// a selection that is not known has the declared type
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy(Article::class, select: $fields)->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy(Article::class, select: [$field])->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy(Article::class, select: ['id', $field])->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy(Article::class, select: ['id' => $field])->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy(Article::class, select: $selectWithManyOptionalFields)->asArray());
		assertType('array<string, bool|float|int|string|null>|null', $scalars->findOneBy(Article::class, select: $fields));
		assertType('array<int|string, array<string, bool|float|int|string|null>>', $scalars->findIndexedBy(Article::class, 'id', select: $fields)->asArray());

		// no selection and an empty one select the fields of the entity
		assertType('list<array{id: int, name: string}>', $scalars->findBy(Author::class)->asArray());
		assertType('list<array{id: int, name: string}>', $scalars->findBy(Author::class, select: [])->asArray());
	}

	/**
	 * @param 'id'|'title' $idOrTitle
	 * @param 'id'|'symbol' $idOrSymbol
	 */
	public function testFieldArgument(ScalarQueries $scalars, string $idOrTitle, string $idOrSymbol, string $field): void
	{
		assertType('list<int|string>', $scalars->findColumnValuesBy(Article::class, $idOrTitle)->asArray());
		assertType('int|string|null', $scalars->findSingleColumnValueBy(Article::class, $idOrTitle));
		assertType('array<int|string, int>', $scalars->findPairsBy(Article::class, $idOrTitle, 'id')->asArray());
		assertType('array<int, int|string>', $scalars->findPairsBy(Article::class, 'id', $idOrTitle)->asArray());
		assertType('array<int|string, array{id: int}>', $scalars->findIndexedBy(Article::class, $idOrTitle, select: ['id'])->asArray());
		assertType('list<int|string|null>', $scalars->findColumnValuesBy(Article::class, $idOrSymbol)->asArray());
		// the criteria narrow each of the fields
		assertType("list<1|string>", $scalars->findColumnValuesBy(Article::class, $idOrTitle, ['id' => 1])->asArray());
		assertType('list<int|string>', $scalars->findColumnValuesBy(Article::class, $idOrSymbol, ['symbol !=' => null])->asArray());
		// a field that is not known
		assertType('list', $scalars->findColumnValuesBy(Article::class, $field)->asArray());
		assertType('array<int|string, int>', $scalars->findPairsBy(Article::class, $field, 'id')->asArray());
	}

	/**
	 * @param class-string<Article>|class-string<Author> $articleOrAuthor
	 * @param class-string<Article> $articleClass
	 */
	public function testEntity(ScalarQueries $scalars, bool $condition, string $articleOrAuthor, string $articleClass): void
	{
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy($condition ? Article::class : Author::class)->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy($condition ? Article::class : Author::class, select: ['id'])->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy($articleOrAuthor)->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy($articleClass)->asArray());
		assertType('list<bool|float|int|string|null>', $scalars->findColumnValuesBy($condition ? Article::class : Author::class, 'id')->asArray());
	}

	/**
	 * A join that is not certainly an inner one is a left join, its fields can be NULL.
	 *
	 * @param 'left'|'inner' $leftOrInner
	 * @param array{author?: 'inner'} $optionalJoin
	 * @param array{author: 'left'|'inner'} $leftOrInnerJoin
	 * @param array<string, 'left'|'inner'> $joins
	 */
	public function testJoinConfig(
		ScalarQueries $scalars,
		bool $condition,
		string $leftOrInner,
		array $optionalJoin,
		array $leftOrInnerJoin,
		array $joins,
	): void
	{
		assertType('list<array{name: string}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'], joinConfig: 'inner')->asArray());
		assertType('list<array{name: string}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'], joinConfig: ['author' => 'inner'])->asArray());
		assertType('list<array{name: string|null}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'])->asArray());
		assertType('list<array{name: string|null}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'], joinConfig: 'left')->asArray());
		assertType('list<array{name: string|null}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'], joinConfig: $leftOrInner)->asArray());
		assertType('list<array{name: string|null}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'], joinConfig: $condition ? 'inner' : 'left')->asArray());
		assertType('list<array{name: string|null}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'], joinConfig: $optionalJoin)->asArray());
		assertType('list<array{name: string|null}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'], joinConfig: $leftOrInnerJoin)->asArray());
		assertType('list<array{name: string|null}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'], joinConfig: $joins)->asArray());
		assertType('list<array{name: string|null}>', $scalars->findBy(ArticleNullableAuthor::class, select: ['author.name'], joinConfig: $condition ? ['author' => 'inner'] : [])->asArray());
		assertType('list<string|null>', $scalars->findColumnValuesBy(ArticleNullableAuthor::class, 'author.name', joinConfig: $optionalJoin)->asArray());
		assertType('list<string>', $scalars->findColumnValuesBy(ArticleNullableAuthor::class, 'author.name', joinConfig: ['author' => 'inner'])->asArray());
	}

	/**
	 * The parameters the unpacked values are passed to are not known, the result has the declared type.
	 *
	 * @param array{0: array<string, mixed>, 1?: array<string, string>} $arguments
	 */
	public function testUnpackedArguments(ScalarQueries $scalars, ArrayQueries $arrays, array $arguments): void
	{
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy(Article::class, ...$arguments)->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy(Article::class, ...['select' => ['id']])->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy(Article::class, ['id' => 1], ...['select' => ['id']])->asArray());
		assertType('list<array<string, bool|float|int|string|null>>', $scalars->findBy(...[Article::class, ['id' => 1]])->asArray());
		assertType('list<array<string, mixed>>', $arrays->findBy(Article::class, ...['select' => ['id']])->asArray());
		// named arguments in any order are followed
		assertType('list<array{id: 1}>', $scalars->findBy(select: ['id'], entity: Article::class, criteria: ['id' => 1])->asArray());
	}

}
