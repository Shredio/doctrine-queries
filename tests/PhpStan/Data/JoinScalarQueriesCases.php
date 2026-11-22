<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\OneToOnePrimary;
use function PHPStan\Testing\assertType;

class JoinScalarQueriesCases
{

	public function testFindPairsByExcludeNull(ScalarQueries $queries): void
	{
		assertType('array<int, string>', $queries->findPairsBy(OneToOnePrimary::class, 'assoc.id', 'assoc.nullableField', [
			'assoc.nullableField !=' => null,
		])->asArray());
	}

	/**
	 * @param string[] $values
	 */
	public function testFindPairsByListOfValues(ScalarQueries $queries, array $values): void
	{
		assertType('array<int, string>', $queries->findPairsBy(OneToOnePrimary::class, 'assoc.id', 'assoc.nullableField', [
			'assoc.nullableField' => $values,
		])->asArray());
	}


	/**
	 * '**' adds the identifier of the owning side of a one-to-one association, the same as of many-to-one.
	 */
	public function testWildcardWithRelationsSelectsOwningSideOfOneToOne(ScalarQueries $queries): void
	{
		assertType('list<array{normalField: string, assoc: int}>', $queries->findBy(OneToOnePrimary::class, select: ['**'])->asArray());
		assertType('list<array{normalField: string}>', $queries->findBy(OneToOnePrimary::class, select: ['*'])->asArray());
		assertType('list<array{normalField: string}>', $queries->findBy(OneToOnePrimary::class)->asArray());
	}

}
