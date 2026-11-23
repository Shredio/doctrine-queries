<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Criteria\Criteria;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;
use function PHPStan\Testing\assertType;

class AnyOfCriteriaCases
{

	/**
	 * @param array<string, mixed> $dynamicAlternative
	 * @param list<array<string, mixed>> $dynamicAlternatives
	 */
	public function testAnyOf(int $id, array $dynamicAlternative, array $dynamicAlternatives, bool $condition): void
	{
		assertType("Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}, array{'title LIKE': '%a%', symbol: null}}>", Criteria::anyOf(['id' => 1], ['title LIKE' => '%a%', 'symbol' => null]));
		assertType('Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: int}}>', Criteria::anyOf(['id' => $id]));
		assertType('Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}, array{id: 2}, array{id: 1}}>', Criteria::anyOf(['id' => 1], ['id' => 2], ['id' => 1]));
		assertType('Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}, array{}}>', Criteria::anyOf(['id' => 1], []));
		assertType('Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{}>', Criteria::anyOf());
		assertType("Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}, array{title: 'a'}}>", Criteria::anyOf(first: ['id' => 1], second: ['title' => 'a']));
		assertType("Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}, array{symbol: null, 0: Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{title: 'a'}, array{title: 'b'}}>}}>", Criteria::anyOf(['id' => 1], ['symbol' => null, Criteria::anyOf(['title' => 'a'], ['title' => 'b'])]));
		assertType("Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}, array{title: 'a'}}>", Criteria::anyOf(...[['id' => 1], ['title' => 'a']]));
		assertType('Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}, array{id: 2}, array{id: 3}, array{id: 4}}>', Criteria::anyOf(['id' => 1], ...[['id' => 2]], ...[['id' => 3], ['id' => 4]]));
		assertType('Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}, array<string, mixed>}>', Criteria::anyOf(['id' => 1], $dynamicAlternative));
		assertType('Shredio\\DoctrineQueries\\Criteria\\AnyOf<list<array<string, mixed>>>', Criteria::anyOf(...$dynamicAlternatives));
		assertType("Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}|array{title: 'a'}, array{id: 2}}>", Criteria::anyOf($condition ? ['id' => 1] : ['title' => 'a'], ['id' => 2]));
		assertType("Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}}>|Shredio\\DoctrineQueries\\Criteria\\AnyOf<array{array{id: 1}, array{title: 'a'}}>", $condition ? Criteria::anyOf(['id' => 1]) : Criteria::anyOf(['id' => 1], ['title' => 'a']));
		assertType('static-Closure<TAlternative of array<mixed>>(TAlternative ...): Shredio\\DoctrineQueries\\Criteria\\AnyOf<list<TAlternative>>', Criteria::anyOf(...));
	}

	public function testAlternativesDoNotNarrowTheSelectedFields(ArrayQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array{id: 1, symbol: Tests\\Doctrine\\Symbol}>', $queries->findBy(Article::class, ['id' => 1, 'symbol !=' => null], select: ['id', 'symbol']));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array{id: int, symbol: Tests\\Doctrine\\Symbol|null}>', $queries->findBy(Article::class, [Criteria::anyOf(['id' => 1, 'symbol !=' => null], ['title' => 'a'])], select: ['id', 'symbol']));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array{id: int, symbol: Tests\\Doctrine\\Symbol|null}>', $queries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2])], select: ['id', 'symbol']));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array{id: int, symbol: Tests\\Doctrine\\Symbol|null}>', $queries->findBy(Article::class, [Criteria::anyOf(['id' => 1, 'symbol !=' => null])], select: ['id', 'symbol']));
		assertType('array{id: int, symbol: Tests\\Doctrine\\Symbol|null}|null', $queries->findOneBy(Article::class, [Criteria::anyOf(['symbol !=' => null], ['symbol !=' => null, 'id' => 1])], select: ['id', 'symbol']));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseIndexedResults<int, array{symbol: Tests\\Doctrine\\Symbol|null}>', $queries->findIndexedBy(Article::class, 'id', [Criteria::anyOf(['id' => 1], ['id' => 2])], select: ['symbol']));
	}

	public function testCriteriaAroundTheGroupNarrowTheSelectedFields(ArrayQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array{id: 1, symbol: Tests\\Doctrine\\Symbol}>', $queries->findBy(Article::class, ['id' => 1, Criteria::anyOf(['symbol' => null], ['title' => 'a']), 'symbol !=' => null], select: ['id', 'symbol']));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseResults<array{id: 1, symbol: Tests\\Doctrine\\Symbol|null}>', $queries->findBy(Article::class, [Criteria::anyOf(['id' => 2], ['title' => 'a']), 'id' => 1], select: ['id', 'symbol']));
	}

	public function testScalarQueries(ScalarQueries $queries): void
	{
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseColumnValues<string>', $queries->findColumnValuesBy(Article::class, 'symbol', ['symbol !=' => null]));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabaseColumnValues<string|null>', $queries->findColumnValuesBy(Article::class, 'symbol', [Criteria::anyOf(['symbol !=' => null], ['id' => 1])]));
		assertType('Shredio\\DoctrineQueries\\Result\\DatabasePairs<int, string|null>', $queries->findPairsBy(Article::class, 'id', 'symbol', [Criteria::anyOf(['id' => 1, 'symbol !=' => null], ['id' => 2, 'symbol !=' => null])]));
		assertType('int|null', $queries->findSingleColumnValueBy(Article::class, 'id', [Criteria::anyOf(['id' => 1], ['id' => 2])]));
		assertType('1|2|null', $queries->findSingleColumnValueBy(Article::class, 'id', ['id' => [1, 2], Criteria::anyOf(['id' => 1], ['title' => 'a'])]));
	}

}
