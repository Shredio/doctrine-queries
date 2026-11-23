<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\UsageRecord;
use function PHPStan\Testing\assertType;

/**
 * The integers of an integer column mapped with the option "unsigned" are not negative wherever the column is returned.
 * A decimal column mapped as unsigned and a signed column keep their types.
 */
class UnsignedColumnCases
{

	public function testScalars(ScalarQueries $scalars): void
	{
		assertType('list<array{id: int<0, max>, tokens: int<0, max>, balance: int, retries: int<0, max>|null, bytes: int<0, max>, priority: int<0, max>, fee: float|int, previous: int<0, max>|null}>', $scalars->findBy(UsageRecord::class, select: ['id', 'tokens', 'balance', 'retries', 'bytes', 'priority', 'fee', 'previous'])->asArray());
		assertType('array{previousTokens: int<0, max>|null, previousBalance: int|null}|null', $scalars->findOneBy(UsageRecord::class, select: ['previous.tokens' => 'previousTokens', 'previous.balance' => 'previousBalance']));
		assertType('array{tokens: int<0, max>}|null', $scalars->findOneBy(UsageRecord::class, select: ['previous.tokens'], joinConfig: 'inner'));
		assertType('list<int<0, max>>', $scalars->findColumnValuesBy(UsageRecord::class, 'tokens')->asArray());
		assertType('list<int<0, max>|null>', $scalars->findColumnValuesBy(UsageRecord::class, 'retries')->asArray());
		assertType('int<0, max>|null', $scalars->findSingleColumnValueBy(UsageRecord::class, 'tokens', []));
		assertType('array<int<0, max>, int<0, max>>', $scalars->findPairsBy(UsageRecord::class, 'id', 'tokens')->asArray());
		assertType('array<int<0, max>, int>', $scalars->findPairsBy(UsageRecord::class, 'tokens', 'balance')->asArray());
		assertType('array<int<0, max>, array{retries: int<0, max>|null}>', $scalars->findIndexedBy(UsageRecord::class, 'tokens', select: ['retries'])->asArray());
	}

	public function testArrays(ArrayQueries $arrays): void
	{
		assertType('list<array{id: int<0, max>, tokens: int<0, max>, balance: int, retries: int<0, max>|null, bytes: int<0, max>, priority: Tests\\Entity\\Enum\\Priority, fee: numeric-string&uppercase-string, previous: int<0, max>|null}>', $arrays->findBy(UsageRecord::class, select: ['id', 'tokens', 'balance', 'retries', 'bytes', 'priority', 'fee', 'previous'])->asArray());
		assertType('list<int<0, max>>', $arrays->findColumnValuesBy(UsageRecord::class, 'tokens')->asArray());
		assertType('array<int<0, max>, int<0, max>|null>', $arrays->findPairsBy(UsageRecord::class, 'id', 'retries')->asArray());
	}

	public function testNarrowedByCriteria(ScalarQueries $scalars, int $tokens): void
	{
		assertType('list<5|7>', $scalars->findColumnValuesBy(UsageRecord::class, 'tokens', ['tokens' => [5, 7]])->asArray());
		assertType('list<int<0, max>>', $scalars->findColumnValuesBy(UsageRecord::class, 'tokens', ['tokens' => $tokens])->asArray());
		assertType('list<int<1, max>>', $scalars->findColumnValuesBy(UsageRecord::class, 'tokens', ['tokens !=' => 0])->asArray());
		assertType('list<int<0, max>>', $scalars->findColumnValuesBy(UsageRecord::class, 'retries', ['retries !=' => null])->asArray());
		assertType('list<int<0, max>>', $scalars->findColumnValuesBy(UsageRecord::class, 'tokens', ['tokens' => -1])->asArray()); // matches no row
	}

}
