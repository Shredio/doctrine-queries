<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use ArrayIterator;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Generator;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Query\SubQuery;
use Tests\Entity\Article;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\Enum\Priority;
use Tests\Entity\TypedValues;
use Traversable;
use function PHPStan\Testing\assertType;

/**
 * The types of the selected fields are narrowed only by what every returned row certainly matches.
 */
class CriteriaNarrowingCases
{

	/**
	 * Only a value that is known exactly is excluded, a row can contain any other value of the compared type.
	 *
	 * @param 1|2 $oneOrTwo
	 * @param int<1, 5> $range
	 * @param 'a'|'b' $aOrB
	 * @param non-empty-string $nonEmptyString
	 */
	public function testNotEqualValue(
		ScalarQueries $scalars,
		ArrayQueries $arrays,
		int $int,
		int $oneOrTwo,
		int $range,
		?int $nullableInt,
		mixed $mixed,
		string $string,
		string $aOrB,
		string $nonEmptyString,
		bool $bool,
		float $float,
	): void
	{
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => $int], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id <>' => $int], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => $oneOrTwo], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => $range], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => $nullableInt], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => $mixed], select: ['id'])->asArray());
		assertType('list<array{id: int<min, 0>|int<2, max>}>', $scalars->findBy(Article::class, ['id !=' => 1], select: ['id'])->asArray());
		assertType('list<array{id: int<min, 0>|int<2, max>}>', $scalars->findBy(Article::class, ['id <>' => 1], select: ['id'])->asArray());

		assertType('list<array{title: string}>', $scalars->findBy(Article::class, ['title !=' => $string], select: ['title'])->asArray());
		assertType('list<array{title: string}>', $scalars->findBy(Article::class, ['title !=' => $aOrB], select: ['title'])->asArray());
		assertType('list<array{title: string}>', $scalars->findBy(Article::class, ['title !=' => $nonEmptyString], select: ['title'])->asArray());
		assertType('list<array{title: string}>', $scalars->findBy(Article::class, ['title !=' => 'a'], select: ['title'])->asArray());
		assertType('list<array{title: non-empty-string}>', $scalars->findBy(Article::class, ['title !=' => ''], select: ['title'])->asArray());

		assertType('list<array{flag: bool}>', $arrays->findBy(TypedValues::class, ['flag !=' => $bool], select: ['flag'])->asArray());
		assertType('list<array{flag: false}>', $arrays->findBy(TypedValues::class, ['flag !=' => true], select: ['flag'])->asArray());
		assertType('list<array{flag: 0|1}>', $scalars->findBy(TypedValues::class, ['flag !=' => $bool], select: ['flag'])->asArray());
		assertType('list<array{flag: 0|1}>', $scalars->findBy(TypedValues::class, ['flag !=' => true], select: ['flag'])->asArray());
		assertType('list<array{ratio: float}>', $arrays->findBy(TypedValues::class, ['ratio !=' => $float], select: ['ratio'])->asArray());
		assertType('list<array{big: int}>', $arrays->findBy(TypedValues::class, ['big !=' => $int], select: ['big'])->asArray());

		// a value of another kind than the column excludes nothing from its type
		assertType('list<array{small: int}>', $scalars->findBy(TypedValues::class, ['small !=' => $string], select: ['small'])->asArray());
		assertType('list<array{small: int}>', $scalars->findBy(TypedValues::class, ['small !=' => '1'], select: ['small'])->asArray());
		assertType('list<array{type: Tests\Entity\Enum\ArticleType}>', $arrays->findBy(Article::class, ['type !=' => 'blog'], select: ['type'])->asArray());
		assertType('list<array{type: string}>', $scalars->findBy(Article::class, ['type !=' => 'blog'], select: ['type'])->asArray());
	}

	/**
	 * @param 1|2 $oneOrTwo
	 * @param list<int> $ints
	 * @param non-empty-list<int> $nonEmptyInts
	 * @param array{0: 1, 1?: 2} $listWithOptionalItem
	 * @param array{1}|array{2} $oneOfLists
	 */
	public function testNotInList(
		ScalarQueries $scalars,
		int $int,
		int $oneOrTwo,
		array $ints,
		array $nonEmptyInts,
		array $listWithOptionalItem,
		array $oneOfLists,
	): void
	{
		assertType('list<array{id: int<min, 0>|int<3, max>}>', $scalars->findBy(Article::class, ['id !=' => [1, 2]], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => [$int]], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => [$oneOrTwo]], select: ['id'])->asArray());
		assertType('list<array{id: int<min, 0>|int<2, max>}>', $scalars->findBy(Article::class, ['id !=' => [1, $int]], select: ['id'])->asArray());
		assertType('list<array{id: int<min, 0>|int<2, max>}>', $scalars->findBy(Article::class, ['id !=' => [$oneOrTwo, 1]], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => $ints], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => $nonEmptyInts], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => []], select: ['id'])->asArray());
		// the optional item may not be in the list, and only one of the lists is compared
		assertType('list<array{id: int<min, 0>|int<2, max>}>', $scalars->findBy(Article::class, ['id !=' => $listWithOptionalItem], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id !=' => $oneOfLists], select: ['id'])->asArray());
	}

	/**
	 * "field != value" and NOT IN are not true for NULL, so the rows with NULL are excluded as well,
	 * unless the criteria can be skipped (an empty list) or the compared values are not known.
	 *
	 * @param list<int> $ints
	 * @param array{0?: 1} $listWithOptionalItemOnly
	 */
	public function testNotEqualExcludesNull(
		ScalarQueries $scalars,
		ArrayQueries $arrays,
		int $int,
		?int $nullableInt,
		mixed $mixed,
		bool $bool,
		array $ints,
		array $listWithOptionalItemOnly,
		ArticleType $type,
	): void
	{
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $int], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int<min, 4>|int<6, max>}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => 5], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableFlag: bool}>', $arrays->findBy(TypedValues::class, ['nullableFlag !=' => $bool], select: ['nullableFlag'])->asArray());
		assertType('list<array{nullableFlag: false}>', $arrays->findBy(TypedValues::class, ['nullableFlag !=' => true], select: ['nullableFlag'])->asArray());
		assertType('list<array{symbol: string}>', $scalars->findBy(Article::class, ['symbol !=' => 'a'], select: ['symbol'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => null], select: ['nullableNumber'])->asArray());

		assertType('list<array{nullableNumber: int<min, 4>|int<6, max>}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => [5]], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int<min, 4>|int<6, max>}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => [5, null]], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => [null]], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => [$int]], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => [$nullableInt]], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => [$type]], select: ['nullableNumber'])->asArray());

		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => []], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $ints], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $listWithOptionalItemOnly], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $nullableInt], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $mixed], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $bool ? [1] : null], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $bool ? [] : null], select: ['nullableNumber'])->asArray());
	}

	public function testNotEqualInOtherFinders(ScalarQueries $scalars, int $int, string $string): void
	{
		assertType('array{id: int}|null', $scalars->findOneBy(Article::class, ['id !=' => $int], select: ['id']));
		assertType('list<int>', $scalars->findColumnValuesBy(Article::class, 'id', ['id !=' => $int])->asArray());
		assertType('int|null', $scalars->findSingleColumnValueBy(Article::class, 'id', ['id !=' => $int]));
		assertType('array<int, string>', $scalars->findPairsBy(Article::class, 'id', 'title', ['id !=' => $int, 'title !=' => $string])->asArray());
		assertType('array<int, array{title: string}>', $scalars->findIndexedBy(Article::class, 'id', ['id !=' => $int], select: ['title'])->asArray());
		assertType('array<string, int>', $scalars->findPairsBy(Article::class, 'symbol', 'id', ['symbol !=' => $string])->asArray());
		assertType('list<array{author: int}>', $scalars->findBy(Article::class, ['author !=' => $int], select: ['author'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['author.id !=' => $int], select: ['author.id'])->asArray());
		assertType('list<array{id: int<min, 0>|int<2, max>}>', $scalars->findBy(Article::class, ['author.id !=' => 1], select: ['author.id'])->asArray());
		// the criteria of another field does not narrow the selected one
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['author.id !=' => 1], select: ['id'])->asArray());
	}

	/**
	 * <, <=, >, >=, LIKE and NOT LIKE are not true for NULL either.
	 */
	public function testOtherOperatorsExcludeNull(ScalarQueries $scalars, ArrayQueries $arrays, int $int, string $string, DateTimeImmutable $dateTime): void
	{
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber >' => 5], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber >=' => $int], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber <' => $int], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber <=' => 5], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableMoment: DateTimeImmutable}>', $arrays->findBy(TypedValues::class, ['nullableMoment >' => $dateTime], select: ['nullableMoment'])->asArray());
		assertType('list<array{symbol: string}>', $scalars->findBy(Article::class, ['symbol LIKE' => 'a%'], select: ['symbol'])->asArray());
		assertType('list<array{symbol: string}>', $scalars->findBy(Article::class, ['symbol like' => $string], select: ['symbol'])->asArray());
		assertType('list<array{symbol: string}>', $scalars->findBy(Article::class, ['symbol NOT LIKE' => 'a%'], select: ['symbol'])->asArray());
		assertType('array<string, int>', $scalars->findPairsBy(Article::class, 'symbol', 'id', ['symbol LIKE' => 'a%'])->asArray());
		// an unsupported operator is reported, it does not narrow, and neither does a criteria of another field
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber BETWEEN' => 5], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['id >' => 5], select: ['nullableNumber'])->asArray());
	}

	/**
	 * A row matches every criteria of the field, whatever their order is.
	 */
	public function testMoreCriteriaOfOneField(ScalarQueries $scalars, ArrayQueries $arrays, int $int): void
	{
		assertType('list<array{id: 2}>', $scalars->findBy(Article::class, ['id' => [1, 2], 'id !=' => 1], select: ['id'])->asArray());
		assertType('list<array{id: 2}>', $scalars->findBy(Article::class, ['id !=' => 1, 'id' => [1, 2]], select: ['id'])->asArray());
		assertType('list<array{id: int<min, 0>|int<3, max>}>', $scalars->findBy(Article::class, ['id !=' => 1, 'id <>' => 2], select: ['id'])->asArray());
		assertType('list<array{id: 5}>', $scalars->findBy(Article::class, ['id >' => 1, 'id' => 5, 'id <' => $int], select: ['id'])->asArray());
		assertType('list<array{nullableNumber: int<min, 4>|int<6, max>}>', $arrays->findBy(TypedValues::class, ['nullableNumber >' => 1, 'nullableNumber !=' => 5], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: 1|2}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => [1, 2, null], 'nullableNumber !=' => null], select: ['nullableNumber'])->asArray());
		// criteria that exclude each other match no row, the type narrowed so far is kept
		assertType('list<array{id: 1}>', $scalars->findBy(Article::class, ['id' => 1, 'id !=' => 1], select: ['id'])->asArray());
		assertType("list<array{title: 'x'}>", $scalars->findBy(Article::class, ['title' => 'x', 'title =' => 'y'], select: ['title'])->asArray());
		assertType('list<array{nullableNumber: null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => null, 'nullableNumber >' => 1], select: ['nullableNumber'])->asArray());
	}

	/**
	 * A case of an enum narrows a column converted to the enum to the case, a column returned as it is stored to its value.
	 *
	 * @param 'blog'|'news' $typeValue
	 */
	public function testEnumCases(ScalarQueries $scalars, ArrayQueries $arrays, ArticleType $type, Priority $priority, bool $condition, string $typeValue): void
	{
		assertType('list<array{type: Tests\Entity\Enum\ArticleType::Blog}>', $arrays->findBy(Article::class, ['type' => ArticleType::Blog], select: ['type'])->asArray());
		assertType('list<array{type: Tests\Entity\Enum\ArticleType::News}>', $arrays->findBy(Article::class, ['type !=' => ArticleType::Blog], select: ['type'])->asArray());
		assertType('list<array{type: Tests\Entity\Enum\ArticleType::Blog}>', $arrays->findBy(Article::class, ['type' => [ArticleType::Blog]], select: ['type'])->asArray());
		assertType('list<array{type: Tests\Entity\Enum\ArticleType::Blog|Tests\Entity\Enum\ArticleType::News}>', $arrays->findBy(Article::class, ['type' => [ArticleType::Blog, ArticleType::News]], select: ['type'])->asArray());
		assertType('list<array{type: Tests\Entity\Enum\ArticleType::News}>', $arrays->findBy(Article::class, ['type !=' => [ArticleType::Blog]], select: ['type'])->asArray());
		assertType('list<array{type: Tests\Entity\Enum\ArticleType::Blog}>', $arrays->findBy(Article::class, ['type' => $condition ? ArticleType::Blog : [ArticleType::Blog]], select: ['type'])->asArray());
		assertType('list<array{priority: Tests\Entity\Enum\Priority::High}>', $arrays->findBy(TypedValues::class, ['priority' => Priority::High], select: ['priority'])->asArray());
		// an enum that is not a single case, and the value of a case, do not narrow the enum
		assertType('list<array{type: Tests\Entity\Enum\ArticleType}>', $arrays->findBy(Article::class, ['type' => $type], select: ['type'])->asArray());
		assertType('list<array{type: Tests\Entity\Enum\ArticleType}>', $arrays->findBy(Article::class, ['type !=' => $type], select: ['type'])->asArray());
		assertType('list<array{type: Tests\Entity\Enum\ArticleType}>', $arrays->findBy(Article::class, ['type' => 'blog'], select: ['type'])->asArray());
		assertType('list<array{type: Tests\Entity\Enum\ArticleType}>', $arrays->findBy(Article::class, ['type !=' => 'blog'], select: ['type'])->asArray());

		assertType("list<array{type: 'blog'}>", $scalars->findBy(Article::class, ['type' => ArticleType::Blog], select: ['type'])->asArray());
		assertType("list<array{type: 'blog'|'news'}>", $scalars->findBy(Article::class, ['type' => [ArticleType::Blog, ArticleType::News]], select: ['type'])->asArray());
		assertType("list<array{type: 'blog'|'news'}>", $scalars->findBy(Article::class, ['type' => $typeValue], select: ['type'])->asArray());
		assertType('list<array{type: string}>', $scalars->findBy(Article::class, ['type' => $type], select: ['type'])->asArray());
		assertType('list<array{type: string}>', $scalars->findBy(Article::class, ['type !=' => ArticleType::Blog], select: ['type'])->asArray());
		assertType('list<array{priority: 2}>', $scalars->findBy(TypedValues::class, ['priority' => Priority::High], select: ['priority'])->asArray());
		assertType('list<array{priority: int<min, 1>|int<3, max>}>', $scalars->findBy(TypedValues::class, ['priority !=' => Priority::High], select: ['priority'])->asArray());
		assertType('list<array{priority: int}>', $scalars->findBy(TypedValues::class, ['priority' => $priority], select: ['priority'])->asArray());

		// a column that is not an enum is compared with the value of the case
		assertType('list<array{small: 2}>', $arrays->findBy(TypedValues::class, ['small' => Priority::High], select: ['small'])->asArray());
		assertType('list<array{small: int<min, 1>|int<3, max>}>', $arrays->findBy(TypedValues::class, ['small !=' => Priority::High], select: ['small'])->asArray());
		assertType('list<array{nullableNumber: int<min, 1>|int<3, max>}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => Priority::High], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: 2}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => Priority::High], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => ArticleType::Blog], select: ['nullableNumber'])->asArray());
	}

	/**
	 * A value that is one of several: a literal or null, a literal or a list.
	 *
	 * @param 1|null $oneOrNull
	 */
	public function testOneOfValues(ScalarQueries $scalars, ArrayQueries $arrays, bool $condition, ?int $oneOrNull, int $int): void
	{
		assertType('list<array{nullableNumber: 1|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $oneOrNull], select: ['nullableNumber'])->asArray());
		assertType('list<array{id: 1}>', $scalars->findBy(Article::class, ['id' => $oneOrNull], select: ['id'])->asArray());
		assertType('list<array{id: 1|2|3}>', $scalars->findBy(Article::class, ['id' => $condition ? 1 : [2, 3]], select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id' => $condition ? 1 : [2, $int]], select: ['id'])->asArray());
		assertType('list<array{nullableNumber: 1|2|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $condition ? 1 : [2, null]], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $oneOrNull], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $condition ? 1 : [2, 3]], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber !=' => $condition ? 1 : []], select: ['nullableNumber'])->asArray());
	}

	/**
	 * Criteria built by conditions: an optional item and the items of one of several arrays are not matched by every row.
	 *
	 * @param array{id?: 1} $optionalCriteria
	 * @param array{id: 1}|array{id: 2} $oneOfCriteria
	 * @param array{id: 1}|array{title: 'x'} $oneOfCriteriaOfOtherFields
	 * @param array{id: 1}|array{} $criteriaOrNone
	 * @param array{id: 1}|array<string, mixed> $criteriaOrUnknown
	 * @param array<string, mixed> $unknownCriteria
	 */
	public function testConditionalCriteria(
		ScalarQueries $scalars,
		bool $condition,
		array $optionalCriteria,
		array $oneOfCriteria,
		array $oneOfCriteriaOfOtherFields,
		array $criteriaOrNone,
		array $criteriaOrUnknown,
		array $unknownCriteria,
	): void
	{
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, $optionalCriteria, select: ['id'])->asArray());
		assertType('list<array{id: 1|2}>', $scalars->findBy(Article::class, $oneOfCriteria, select: ['id'])->asArray());
		assertType('list<array{id: int, title: string}>', $scalars->findBy(Article::class, $oneOfCriteriaOfOtherFields, select: ['id', 'title'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, $criteriaOrNone, select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, $criteriaOrUnknown, select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, $unknownCriteria, select: ['id'])->asArray());
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, ['id' => 1, ...$unknownCriteria], select: ['id'])->asArray());
		assertType('list<array{id: 1|2}>', $scalars->findBy(Article::class, $condition ? ['id' => 1] : ['id' => 2], select: ['id'])->asArray());
		assertType("list<array{id: 1, symbol: string|null}>", $scalars->findBy(Article::class, $condition ? ['id' => 1] : ['id' => 1, 'symbol !=' => null], select: ['id', 'symbol'])->asArray());

		$criteria = [];
		if ($condition) {
			$criteria['id'] = 1;
		}
		assertType('list<array{id: int}>', $scalars->findBy(Article::class, $criteria, select: ['id'])->asArray());

		$criteria = ['title' => 'x'];
		if ($condition) {
			$criteria['id !='] = 1;
			$criteria['symbol !='] = null;
		}
		assertType("list<array{id: int, title: 'x', symbol: string|null}>", $scalars->findBy(Article::class, $criteria, select: ['id', 'title', 'symbol'])->asArray());
		assertType('list<string|null>', $scalars->findColumnValuesBy(Article::class, 'symbol', $criteria)->asArray());
		assertType("array<'x', string|null>", $scalars->findPairsBy(Article::class, 'title', 'symbol', $criteria)->asArray());
		assertType("array<'x', array{symbol: string|null}>", $scalars->findIndexedBy(Article::class, 'title', $criteria, select: ['symbol'])->asArray());
		assertType("array{title: 'x', symbol: string|null}|null", $scalars->findOneBy(Article::class, $criteria, select: ['title', 'symbol']));

		$criteria = ['symbol !=' => null];
		if ($condition) {
			$criteria['id'] = 1;
		} else {
			$criteria['id'] = [2, 3];
		}
		assertType('list<array{id: 1|2|3, symbol: string}>', $scalars->findBy(Article::class, $criteria, select: ['id', 'symbol'])->asArray());
	}

	/**
	 * A value that can be null, or iterate a null, is compared by IS NULL, the rows with NULL match.
	 *
	 * @param list<int> $ints
	 * @param list<int|null> $intsOrNull
	 * @param int|list<int> $intOrInts
	 * @param int|list<int|null> $intOrIntsOrNull
	 * @param array<mixed> $mixedArray
	 * @param iterable<int> $iterable
	 * @param iterable<int|null> $iterableWithNull
	 * @param Generator<int, int> $generator
	 * @param Generator<int, int|null> $generatorWithNull
	 * @param Traversable<int, int|null> $traversableWithNull
	 * @param ArrayIterator<int, int> $iterator
	 */
	public function testEqualValueThatCanBeNull(
		ArrayQueries $arrays,
		int $int,
		?int $nullableInt,
		mixed $mixed,
		bool $condition,
		array $ints,
		array $intsOrNull,
		int|array $intOrInts,
		int|array $intOrIntsOrNull,
		array $mixedArray,
		array $untypedArray,
		iterable $iterable,
		iterable $iterableWithNull,
		Generator $generator,
		Generator $generatorWithNull,
		Traversable $traversableWithNull,
		ArrayIterator $iterator,
		ArrayIterator $untypedIterator,
	): void
	{
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $nullableInt], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $mixed], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $intsOrNull], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $intOrIntsOrNull], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $mixedArray], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $untypedArray], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $iterableWithNull], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $generatorWithNull], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $traversableWithNull], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $untypedIterator], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => [$int, $nullableInt]], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => [$int, $mixed]], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: 1|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $condition ? 1 : null], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: 1|null}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $condition ? [1] : null], select: ['nullableNumber'])->asArray());

		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $int], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $ints], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $intOrInts], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $iterable], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $generator], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $iterator], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: 1|2}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $condition ? [1] : 2], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: 1|2}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => [1, 2]], select: ['nullableNumber'])->asArray());
	}

	/**
	 * An object is compared as a list only when it is Traversable, other objects are never null.
	 */
	public function testEqualObject(
		ArrayQueries $arrays,
		DateTimeImmutable $dateTime,
		DateTimeInterface $dateTimeInterface,
		QueryBuilder $queryBuilder,
		Query $query,
		SubQuery $subQuery,
		ArticleType $type,
		BackedEnum $backedEnum,
		object $object,
	): void
	{
		assertType('list<array{nullableMoment: DateTimeImmutable}>', $arrays->findBy(TypedValues::class, ['nullableMoment' => $dateTime], select: ['nullableMoment'])->asArray());
		assertType('list<array{nullableMoment: DateTimeImmutable}>', $arrays->findBy(TypedValues::class, ['nullableMoment' => $dateTimeInterface], select: ['nullableMoment'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $queryBuilder], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $query], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $subQuery], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $type], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $backedEnum], select: ['nullableNumber'])->asArray());
		assertType('list<array{nullableNumber: int}>', $arrays->findBy(TypedValues::class, ['nullableNumber' => $object], select: ['nullableNumber'])->asArray());
	}

}
