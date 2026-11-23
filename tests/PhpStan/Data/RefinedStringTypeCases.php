<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Keyword;
use function PHPStan\Testing\assertType;

/**
 * The type of a field is the one the descriptor of its DBAL type returns, so a refined string type
 * (non-empty-string of NonEmptyStringTypeDescriptor) is kept in the results. A PHPDoc of the property is not read.
 */
class RefinedStringTypeCases
{

	public function testScalars(ScalarQueries $scalars): void
	{
		assertType('list<array{name: non-empty-string}>', $scalars->findBy(Keyword::class, select: ['name'])->asArray());
		assertType('list<array{description: non-empty-string|null}>', $scalars->findBy(Keyword::class, select: ['description'])->asArray());
		assertType(
			'list<array{id: int, name: non-empty-string, description: non-empty-string|null}>',
			$scalars->findBy(Keyword::class, select: ['*'])->asArray(),
		);
		assertType('array{name: non-empty-string, label: non-empty-string|null}|null', $scalars->findOneBy(Keyword::class, select: [
			'name',
			'description' => 'label',
		]));
		assertType('list<non-empty-string>', $scalars->findColumnValuesBy(Keyword::class, 'name')->asArray());
		assertType('list<non-empty-string|null>', $scalars->findColumnValuesBy(Keyword::class, 'description')->asArray());
		assertType('non-empty-string|null', $scalars->findSingleColumnValueBy(Keyword::class, 'name', []));
	}

	public function testArrays(ArrayQueries $arrays): void
	{
		assertType('list<array{name: non-empty-string}>', $arrays->findBy(Keyword::class, select: ['name'])->asArray());
		assertType('list<array{description: non-empty-string|null}>', $arrays->findBy(Keyword::class, select: ['description'])->asArray());
		assertType(
			'list<array{id: int, name: non-empty-string, description: non-empty-string|null}>',
			$arrays->findBy(Keyword::class, select: ['*'])->asArray(),
		);
		assertType('array{name: non-empty-string, label: non-empty-string|null}|null', $arrays->findOneBy(Keyword::class, select: [
			'name',
			'description' => 'label',
		]));
		assertType('list<non-empty-string>', $arrays->findColumnValuesBy(Keyword::class, 'name')->asArray());
		assertType('list<non-empty-string|null>', $arrays->findColumnValuesBy(Keyword::class, 'description')->asArray());
		assertType('non-empty-string|null', $arrays->findSingleColumnValueBy(Keyword::class, 'name', []));
	}

	public function testResultKeys(ScalarQueries $scalars, ArrayQueries $arrays): void
	{
		assertType('array<non-empty-string, int>', $scalars->findPairsBy(Keyword::class, 'name', 'id')->asArray());
		assertType('array<int, non-empty-string|null>', $arrays->findPairsBy(Keyword::class, 'id', 'description')->asArray());
		assertType('array<non-empty-string, array{id: int}>', $scalars->findIndexedBy(Keyword::class, 'name', select: ['id'])->asArray());
		assertType('array<non-empty-string, array{id: int}>', $arrays->findIndexedBy(Keyword::class, 'name', select: ['id'])->asArray());
	}

	/**
	 * @param non-empty-string $nonEmptyString
	 */
	public function testCriteria(ScalarQueries $scalars, ArrayQueries $arrays, string $string, string $nonEmptyString): void
	{
		// A compared value narrows the type, but it never widens it back to string.
		assertType('list<non-empty-string>', $scalars->findColumnValuesBy(Keyword::class, 'name', ['name' => $string])->asArray());
		assertType('list<non-empty-string>', $arrays->findColumnValuesBy(Keyword::class, 'name', ['name' => $nonEmptyString])->asArray());
		assertType("list<'php'>", $scalars->findColumnValuesBy(Keyword::class, 'name', ['name' => 'php'])->asArray());
		assertType("list<'doctrine'|'php'>", $arrays->findColumnValuesBy(Keyword::class, 'name', ['name' => ['php', 'doctrine']])->asArray());
		assertType('list<non-empty-string>', $scalars->findColumnValuesBy(Keyword::class, 'name', ['name !=' => 'php'])->asArray());
		assertType('list<non-empty-string>', $scalars->findColumnValuesBy(Keyword::class, 'name', ['name LIKE' => 'p%'])->asArray());

		// No row contains an empty string, the criteria do not make the type never.
		assertType('list<non-empty-string>', $scalars->findColumnValuesBy(Keyword::class, 'name', ['name' => ''])->asArray());
		assertType('list<non-empty-string>', $arrays->findColumnValuesBy(Keyword::class, 'name', ['name !=' => ''])->asArray());

		assertType('list<non-empty-string>', $scalars->findColumnValuesBy(Keyword::class, 'description', ['description !=' => null])->asArray());
		assertType('list<non-empty-string>', $arrays->findColumnValuesBy(Keyword::class, 'description', ['description LIKE' => 'p%'])->asArray());
		assertType('list<null>', $scalars->findColumnValuesBy(Keyword::class, 'description', ['description' => null])->asArray());
	}

	public function testAggregations(DoctrineQueries $queries): void
	{
		assertType('non-empty-string|null', $queries->minBy(Keyword::class, 'name'));
		assertType('non-empty-string|null', $queries->maxBy(Keyword::class, 'description'));
		assertType('array<non-empty-string, int<1, max>>', $queries->countGroupedBy(Keyword::class, 'name')->asArray());
		assertType('array<int, non-empty-string>', $queries->minGroupedBy(Keyword::class, 'name', 'id')->asArray());
		assertType('array<non-empty-string, int>', $queries->maxGroupedBy(Keyword::class, 'id', 'name')->asArray());
	}

}
