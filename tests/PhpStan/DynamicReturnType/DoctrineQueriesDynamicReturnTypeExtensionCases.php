<?php declare(strict_types = 1);

namespace Tests\PhpStan\DynamicReturnType;

use Shredio\DoctrineQueries\DoctrineQueries;
use Tests\Entity\Article;
use Tests\Entity\FavouritePrompt;
use Tests\Entity\TypedValues;
use Tests\Entity\UsageRecord;
use function PHPStan\Testing\assertType;

final readonly class DoctrineQueriesDynamicReturnTypeExtensionCases
{

	public function testStaticTypedArray(DoctrineQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<array{id: int}>', $queries->existsManyBy(Article::class, [
			['id' => 1],
			['id' => 2],
		]));
	}

	public function testStaticInvalidTypedDoubleKeyArray(DoctrineQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<*NEVER*>', $queries->existsManyBy(Article::class, [
			['id' => 1, 'name' => 'A'],
			['id' => 2, 'name' => 'B'],
		]));
	}

	public function testStaticTypedDoubleKeyArray(DoctrineQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<array{account: int, prompt: int}>', $queries->existsManyBy(FavouritePrompt::class, [
			['account' => 1, 'prompt' => 2],
			['account' => 2, 'prompt' => 3],
		]));
	}

	/**
	 * @param list<array{ id: string }> $values
	 */
	public function testConstantArray(DoctrineQueries $queries, array $values): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<array{id: int}>', $queries->existsManyBy(Article::class, $values));
	}

	/**
	 * @param list<array<string, mixed>> $values
	 */
	public function testDynamicArray(DoctrineQueries $queries, array $values): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<*NEVER*>', $queries->existsManyBy(Article::class, $values));
	}

	public function testNoArray(DoctrineQueries $queries, mixed $value): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<*NEVER*>', $queries->existsManyBy(Article::class, $value));
	}

	/**
	 * @param array<array{ id: non-empty-string }> $values
	 */
	public function testInvalidNonEmptyString(DoctrineQueries $queries, array $values): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<array{id: int}>', $queries->existsManyBy(Article::class, $values));
	}

	public function testNotAnEntity(DoctrineQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseExistenceResults<array<string, bool|float|int|string|null>>', $queries->existsManyBy(\stdClass::class, [['id' => 1]]));
	}

	public function testToManyAssociation(DoctrineQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseExistenceResults<*NEVER*>', $queries->existsManyBy(Article::class, [['hashTags' => 1]]));
	}

	public function testNullableField(DoctrineQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseExistenceResults<array{id: int, symbol: string|null}>', $queries->existsManyBy(Article::class, [['id' => 1, 'symbol' => null]]));
	}
	public function testCountGroupedByKey(DoctrineQueries $queries, string $groupBy): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<1, max>>', $queries->countGroupedBy(Article::class, 'author'));
		assertType('array<int, int<1, max>>', $queries->countGroupedBy(Article::class, 'author')->asArray());
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int<1, max>>', $queries->countGroupedBy(Article::class, 'author.name'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int<1, max>>', $queries->countGroupedBy(Article::class, 'symbol'));
		assertType("Shredio\\DoctrineQueries\\Result\\DatabasePairs<'other'|'sym', int<1, max>>", $queries->countGroupedBy(Article::class, 'symbol', ['symbol' => ['sym', 'other']]));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int<1, max>>', $queries->countGroupedBy(Article::class, 'createdAt'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int<1, max>>', $queries->countGroupedBy(Article::class, 'type'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<1, max>>', $queries->countGroupedBy(Article::class, criteria: ['id' => 1], groupBy: 'author.role'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int|string, int<1, max>>', $queries->countGroupedBy(Article::class, $groupBy));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int|string, int<1, max>>', $queries->countGroupedBy(Article::class, 'unknown'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<(int|string), int<0, max>>', $queries->countGroupedBy(\stdClass::class, 'id'));
	}

	public function testCountGroupedByCount(DoctrineQueries $queries, string $field, ?string $optionalField): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<1, max>>', $queries->countGroupedBy(Article::class, 'author', field: null));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<1, max>>', $queries->countGroupedBy(Article::class, 'author', field: 'id'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<0, max>>', $queries->countGroupedBy(Article::class, 'author', field: 'symbol'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<1, max>>', $queries->countGroupedBy(Article::class, 'author', ['symbol !=' => null], 'symbol'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<0, max>>', $queries->countGroupedBy(Article::class, 'author', field: 'author.role'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<0, max>>', $queries->countGroupedBy(Article::class, 'author', field: 'author.role.name'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<1, max>>', $queries->countGroupedBy(Article::class, 'author', field: 'author.role.name', joinConfig: 'inner'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<0, max>>', $queries->countGroupedBy(Article::class, 'author', field: 'hashTags.hashtag'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<0, max>>', $queries->countGroupedBy(Article::class, 'author', field: $field));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<0, max>>', $queries->countGroupedBy(Article::class, 'author', field: $optionalField));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int<0, max>>', $queries->countGroupedBy(Article::class, 'author', field: 'unknown'));
	}

	public function testSumBy(DoctrineQueries $queries, string $field): void
	{
		assertType('int|null', $queries->sumBy(TypedValues::class, 'small'));
		assertType('int|null', $queries->sumBy(TypedValues::class, 'big'));
		assertType('numeric-string|null', $queries->sumBy(TypedValues::class, 'price'));
		assertType('float|null', $queries->sumBy(TypedValues::class, 'ratio'));
		assertType('int|null', $queries->sumBy(TypedValues::class, 'priority'));
		assertType('int|null', $queries->sumBy(TypedValues::class, 'small', ['small' => 7]));
		assertType('int|null', $queries->sumBy(Article::class, 'author.id', joinConfig: 'inner'));
		assertType('float|int|numeric-string|null', $queries->sumBy(TypedValues::class, $field));
		assertType('float|int|numeric-string|null', $queries->sumBy(TypedValues::class, 'label'));
		assertType('float|int|numeric-string|null', $queries->sumBy(TypedValues::class, 'unknown'));
		assertType('float|int|numeric-string|null', $queries->sumBy(Article::class, 'author'));
		assertType('float|int|numeric-string|null', $queries->sumBy(\stdClass::class, 'id'));
	}

	public function testMinByAndMaxBy(DoctrineQueries $queries, string $field): void
	{
		assertType('int|null', $queries->minBy(TypedValues::class, 'small'));
		assertType('(numeric-string&uppercase-string)|null', $queries->maxBy(TypedValues::class, 'price'));
		assertType('float|null', $queries->maxBy(TypedValues::class, 'ratio'));
		assertType('DateTimeImmutable|null', $queries->minBy(TypedValues::class, 'day'));
		assertType('Tests\\Entity\\Enum\\ArticleType|null', $queries->maxBy(TypedValues::class, 'kind'));
		assertType('Tests\\Entity\\Enum\\Priority|null', $queries->maxBy(TypedValues::class, 'priority'));
		assertType('bool|null', $queries->maxBy(TypedValues::class, 'flag'));
		assertType('DateTimeImmutable|null', $queries->maxBy(TypedValues::class, 'nullableMoment'));
		assertType('Tests\\Doctrine\\Symbol|null', $queries->minBy(Article::class, 'symbol'));
		assertType('string|null', $queries->minBy(Article::class, 'author.role.name', joinConfig: 'inner'));
		assertType('7|null', $queries->minBy(TypedValues::class, 'small', ['small' => 7]));
		assertType('mixed', $queries->maxBy(TypedValues::class, $field));
		assertType('mixed', $queries->maxBy(TypedValues::class, 'unknown'));
		assertType('mixed', $queries->maxBy(\stdClass::class, 'id'));
	}

	public function testGroupedAggregations(DoctrineQueries $queries, string $field, string $groupBy): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int>', $queries->sumGroupedBy(TypedValues::class, 'small', 'label'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int|null>', $queries->sumGroupedBy(TypedValues::class, 'nullableNumber', 'label'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int>', $queries->sumGroupedBy(TypedValues::class, 'nullableNumber', 'label', ['nullableNumber !=' => null]));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, numeric-string>', $queries->sumGroupedBy(TypedValues::class, 'price', 'kind'));
		assertType('array<int, int>', $queries->sumGroupedBy(Article::class, 'id', 'author')->asArray());
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, int|null>', $queries->sumGroupedBy(Article::class, 'author.role.id', 'author'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, DateTimeImmutable>', $queries->minGroupedBy(TypedValues::class, 'day', 'label'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, DateTimeImmutable|null>', $queries->maxGroupedBy(TypedValues::class, 'nullableMoment', 'priority'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, Tests\\Entity\\Enum\\ArticleType>', $queries->maxGroupedBy(TypedValues::class, 'kind', 'day'));
		assertType('array<int, int>', $queries->maxGroupedBy(Article::class, 'id', criteria: ['id >' => 1], groupBy: 'author')->asArray());
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int|string, float|int|numeric-string|null>', $queries->sumGroupedBy(TypedValues::class, $field, $groupBy));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int|string, mixed>', $queries->minGroupedBy(TypedValues::class, $field, $groupBy));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<(int|string), float|int|numeric-string|null>', $queries->sumGroupedBy(\stdClass::class, 'id', 'id'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<(int|string), mixed>', $queries->maxGroupedBy(\stdClass::class, 'id', 'id'));
	}

	public function testAggregationsOfUnsignedColumns(DoctrineQueries $queries): void
	{
		assertType('int<0, max>|null', $queries->sumBy(UsageRecord::class, 'tokens'));
		assertType('int<0, max>|null', $queries->sumBy(UsageRecord::class, 'retries'));
		assertType('int<0, max>|null', $queries->sumBy(UsageRecord::class, 'bytes'));
		assertType('int<0, max>|null', $queries->sumBy(UsageRecord::class, 'priority'));
		assertType('int<0, max>|null', $queries->sumBy(UsageRecord::class, 'previous.tokens'));
		assertType('int|null', $queries->sumBy(UsageRecord::class, 'balance'));
		assertType('numeric-string|null', $queries->sumBy(UsageRecord::class, 'fee'));
		assertType('int|null', $queries->sumBy(UsageRecord::class, rand(0, 1) === 1 ? 'tokens' : 'balance'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int<0, max>>', $queries->sumGroupedBy(UsageRecord::class, 'tokens', 'provider'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int<0, max>|null>', $queries->sumGroupedBy(UsageRecord::class, 'retries', 'provider'));
		assertType('array<int<0, max>, int<0, max>>', $queries->sumGroupedBy(UsageRecord::class, 'tokens', 'id')->asArray());
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int<0, max>, int>', $queries->sumGroupedBy(UsageRecord::class, 'balance', 'previous'));
		assertType('int<0, max>|null', $queries->minBy(UsageRecord::class, 'tokens'));
		assertType('int<0, max>|null', $queries->maxBy(UsageRecord::class, 'retries'));
		assertType('int<0, max>|null', $queries->maxBy(UsageRecord::class, 'bytes'));
		assertType('Tests\\Entity\\Enum\\Priority|null', $queries->maxBy(UsageRecord::class, 'priority'));
		assertType('int|null', $queries->minBy(UsageRecord::class, 'balance'));
		assertType('(numeric-string&uppercase-string)|null', $queries->maxBy(UsageRecord::class, 'fee'));
		assertType('7|null', $queries->maxBy(UsageRecord::class, 'tokens', ['tokens' => 7]));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, int<0, max>>', $queries->minGroupedBy(UsageRecord::class, 'tokens', 'provider'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int<0, max>, int<0, max>|null>', $queries->maxGroupedBy(UsageRecord::class, 'retries', 'tokens'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int<0, max>, int<1, max>>', $queries->countGroupedBy(UsageRecord::class, 'tokens'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseExistenceResults<array{id: int<0, max>, tokens: int<0, max>, balance: int}>', $queries->existsManyBy(UsageRecord::class, [['id' => 1, 'tokens' => 2, 'balance' => 3]]));
	}

	public function testSumOfSummableType(DoctrineQueries $queries): void
	{
		assertType('float|null', $queries->sumBy(UsageRecord::class, 'cost'));
		assertType('float|null', $queries->sumBy(UsageRecord::class, 'discount'));
		assertType('float|null', $queries->sumBy(UsageRecord::class, 'previous.cost'));
		assertType('float|null', $queries->sumBy(UsageRecord::class, 'cost', ['cost' => 1.5]));
		assertType('float|int<0, max>|null', $queries->sumBy(UsageRecord::class, rand(0, 1) === 1 ? 'cost' : 'tokens'));
		assertType('float|int|numeric-string|null', $queries->sumBy(UsageRecord::class, 'account'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, float>', $queries->sumGroupedBy(UsageRecord::class, 'cost', 'provider'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, float|null>', $queries->sumGroupedBy(UsageRecord::class, 'discount', 'provider'));
		assertType('array<string, float>', $queries->sumGroupedBy(UsageRecord::class, 'discount', 'provider', ['discount !=' => null])->asArray());
		assertType('float|null', $queries->maxBy(UsageRecord::class, 'cost'));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<string, float|null>', $queries->minGroupedBy(UsageRecord::class, 'discount', 'provider'));
	}

}
