<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use ArrayIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Criteria\Criteria;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\AccountNumber;
use Tests\Doctrine\BinaryId;
use Tests\Entity\InvestmentAccount;
use Tests\Entity\Portfolio;
use Tests\TestCase;

/**
 * Criteria compared with a binary identifier of a custom DBAL type, directly and through an association.
 *
 * Doctrine binds an identifier object by its text form and a list of binary values as text, which silently matches no row.
 * The number of an account is a value object of a custom DBAL type that is not binary and has no text form at all.
 *
 * | Account | portfolio | number |
 * |---------|-----------|--------|
 * | 1       | First     | 1001   |
 * | 2       | First     | 1002   |
 * | 3       | Second    | 2001   |
 *
 * The Empty portfolio has no account.
 */
final class BinaryIdentifierCriteriaTest extends TestCase
{

	use DoctrineContext;

	// Bytes that are not valid UTF-8, as the bytes of a UUID usually are.
	private const string FirstId = '0192f0c1a2b37c4d8e9ffedcba987654';
	private const string SecondId = 'ffeeddccbbaa99887766554433221100';
	private const string EmptyId = '8a00000000000000000000000000ff01';
	private const string UnusedId = '00000000000000000000000000000001';

	/**
	 * @return iterable<string, array{array<array-key, mixed>, list<int>}>
	 */
	public static function provideAccountCriteria(): iterable
	{
		$first = BinaryId::fromHex(self::FirstId);
		$second = BinaryId::fromHex(self::SecondId);
		$unused = BinaryId::fromHex(self::UnusedId);

		yield 'association equals identifier object' => [['portfolio' => $first], [1, 2]];
		yield 'association not equals identifier object' => [['portfolio !=' => $first], [3]];
		yield 'association equals unused identifier object' => [['portfolio' => $unused], []];
		yield 'association equals bytes' => [['portfolio' => $first->bytes], [1, 2]];
		yield 'association not equals bytes' => [['portfolio !=' => $first->bytes], [3]];

		yield 'association in identifier objects' => [['portfolio' => [$first, $second]], [1, 2, 3]];
		yield 'association in single identifier object' => [['portfolio' => [$second]], [3]];
		yield 'association in identifier objects, one unused' => [['portfolio' => [$unused, $first]], [1, 2]];
		yield 'association in identifier objects with string keys' => [['portfolio' => ['first' => $first, 'second' => $second]], [1, 2, 3]];
		yield 'association in traversable of identifier objects' => [['portfolio' => new ArrayIterator([$first, $second])], [1, 2, 3]];
		yield 'association in identifier object or null' => [['portfolio' => [$second, null]], [3]];
		yield 'association not in identifier objects' => [['portfolio !=' => [$first, $unused]], [3]];
		yield 'association in bytes' => [['portfolio' => [$first->bytes, $second->bytes]], [1, 2, 3]];
		yield 'association in identifier object and bytes' => [['portfolio' => [$first->bytes, $second]], [1, 2, 3]];
		yield 'association not in bytes' => [['portfolio !=' => [$second->bytes]], [1, 2]];

		yield 'related identifier equals identifier object' => [['portfolio.id' => $first], [1, 2]];
		yield 'related identifier equals bytes' => [['portfolio.id' => $second->bytes], [3]];
		yield 'related identifier in identifier objects' => [['portfolio.id' => [$first, $second]], [1, 2, 3]];
		yield 'related identifier in bytes' => [['portfolio.id' => [$first->bytes]], [1, 2]];
		yield 'related identifier not in identifier objects' => [['portfolio.id !=' => [$first]], [3]];

		yield 'alternatives' => [[Criteria::anyOf(['portfolio' => $second], ['portfolio' => [$first], 'id >' => 1])], [2, 3]];
		// A value object that does not implement DoctrineParameterAware is bound by the type of its column.
		yield 'value object equals' => [['number' => new AccountNumber(1002)], [2]];
		yield 'value object not equals' => [['number !=' => new AccountNumber(1002)], [1, 3]];
		yield 'value object greater than' => [['number >' => new AccountNumber(1001)], [2, 3]];
		yield 'value object less than or equal' => [['number <=' => new AccountNumber(1002)], [1, 2]];
		yield 'value object without a match' => [['number' => new AccountNumber(9999)], []];
		yield 'in value objects' => [['number' => [new AccountNumber(1001), new AccountNumber(2001)]], [1, 3]];
		yield 'not in value objects' => [['number !=' => [new AccountNumber(1001), new AccountNumber(2001)]], [2]];
		yield 'in value object and database value' => [['number' => [1001, new AccountNumber(1002)]], [1, 2]];
		yield 'value object as a database value' => [['number' => 2001], [3]];
		yield 'value object and identifier object' => [['portfolio' => $first, 'number' => new AccountNumber(1001)], [1]];
		yield 'value object in alternatives' => [[Criteria::anyOf(['number' => new AccountNumber(1001)], ['number >' => new AccountNumber(1002)])], [1, 3]];

		yield 'with another criteria' => [['id <' => 3, 'portfolio' => [$first, $second], 'portfolio.name' => 'First'], [1, 2]];
	}

	/**
	 * @return iterable<string, array{string, array<array-key, mixed>, list<int>}>
	 */
	public static function provideAccountCriteriaForEachQueryType(): iterable
	{
		foreach (self::provideAccountCriteria() as $name => [$criteria, $expectedIds]) {
			foreach (['objects', 'arrays', 'scalars'] as $queryType) {
				yield sprintf('%s (%s)', $name, $queryType) => [$queryType, $criteria, $expectedIds];
			}
		}
	}

	/**
	 * @param array<array-key, mixed> $criteria
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('provideAccountCriteriaForEachQueryType')]
	public function testFindBy(string $queryType, array $criteria, array $expectedIds): void
	{
		$this->persistPortfolios();

		$this->assertSame($expectedIds, $this->assertSingleQuery(fn (): array => $this->findAccountIds($queryType, $criteria)));
	}

	/**
	 * @param array<array-key, mixed> $criteria
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('provideAccountCriteria')]
	public function testCountAndExistsAgreeWithFindBy(array $criteria, array $expectedIds): void
	{
		$this->persistPortfolios();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(count($expectedIds), $queries->countBy(InvestmentAccount::class, $criteria));
		$this->assertSame($expectedIds !== [], $queries->existsBy(InvestmentAccount::class, $criteria));
	}

	public function testEntityAsAssociationValue(): void
	{
		$this->persistPortfolios();
		$em = $this->getEntityManager();
		$first = $em->getReference(Portfolio::class, BinaryId::fromHex(self::FirstId));
		$second = $em->find(Portfolio::class, BinaryId::fromHex(self::SecondId));
		$this->assertNotNull($second);

		foreach (['objects', 'arrays', 'scalars'] as $queryType) {
			$this->assertSame([1, 2], $this->findAccountIds($queryType, ['portfolio' => $first]), $queryType);
			$this->assertSame([3], $this->findAccountIds($queryType, ['portfolio' => $second]), $queryType);
			$this->assertSame([3], $this->findAccountIds($queryType, ['portfolio !=' => $first]), $queryType);
			$this->assertSame([1, 2, 3], $this->findAccountIds($queryType, ['portfolio' => [$first, $second]]), $queryType);
			$this->assertSame([1, 2], $this->findAccountIds($queryType, ['portfolio !=' => [$second]]), $queryType);
			$this->assertSame([1, 2, 3], $this->findAccountIds($queryType, ['portfolio' => [$first, $second->getId()]]), $queryType);
		}

		$this->assertSame(2, $this->createDoctrineQueries()->countBy(InvestmentAccount::class, ['portfolio' => $first]));
	}

	public function testIdentifierOfTheEntityItself(): void
	{
		$this->persistPortfolios();
		$first = BinaryId::fromHex(self::FirstId);
		$empty = BinaryId::fromHex(self::EmptyId);

		$this->assertSame(['First'], $this->findPortfolioNames(['id' => $first]));
		$this->assertSame(['First'], $this->findPortfolioNames(['id' => $first->bytes]));
		$this->assertSame(['Empty', 'Second'], $this->findPortfolioNames(['id !=' => $first]));
		$this->assertSame(['Empty', 'First'], $this->findPortfolioNames(['id' => [$first, $empty, BinaryId::fromHex(self::UnusedId)]]));
		$this->assertSame(['Empty', 'First'], $this->findPortfolioNames(['id' => [$first->bytes, $empty->bytes]]));
		$this->assertSame(['Second'], $this->findPortfolioNames(['id !=' => [$first, $empty]]));
	}

	public function testToManyAssociation(): void
	{
		$this->persistPortfolios();
		$first = BinaryId::fromHex(self::FirstId);
		$second = BinaryId::fromHex(self::SecondId);

		$this->assertSame(['First'], $this->findPortfolioNames(['accounts.portfolio' => $first]));
		$this->assertSame(['First', 'Second'], $this->findPortfolioNames(['accounts.portfolio' => [$first, $second]]));
		$this->assertSame(['Second'], $this->findPortfolioNames(['accounts.portfolio' => [$second->bytes]]));
		$this->assertSame(['Second'], $this->findPortfolioNames(['accounts.portfolio.id' => [$second], 'accounts.id' => 3]));
		$this->assertSame([], $this->findPortfolioNames(['accounts.portfolio' => [$second], 'accounts.id' => 1]));
		$this->assertSame(['Second'], $this->findPortfolioNames(['accounts.number' => new AccountNumber(2001)]));
		$this->assertSame(['First'], $this->findPortfolioNames(['accounts.number' => [new AccountNumber(1002), new AccountNumber(9999)]]));
		$this->assertSame(['First'], $this->findPortfolioNames(['accounts.number >' => new AccountNumber(1001), 'accounts.portfolio' => $first]));
	}

	public function testSubQuery(): void
	{
		$this->persistPortfolios();
		$queries = $this->createDoctrineQueries();
		$second = BinaryId::fromHex(self::SecondId);

		$this->assertSame(
			['Second'],
			$this->findPortfolioNames(['id' => $queries->subQuery(InvestmentAccount::class, ['portfolio' => [$second]], select: ['portfolio'])]),
		);
	}

	public function testDeleteBy(): void
	{
		$this->persistPortfolios();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(2, $queries->deleteBy(InvestmentAccount::class, ['portfolio' => [BinaryId::fromHex(self::FirstId)]]));
		$this->assertSame(1, $queries->deleteBy(InvestmentAccount::class, ['portfolio' => BinaryId::fromHex(self::SecondId)]));
		$this->assertSame(0, $queries->countBy(InvestmentAccount::class));
	}

	public function testExistsManyBy(): void
	{
		$this->persistPortfolios();
		$queries = $this->createDoctrineQueries();
		$first = BinaryId::fromHex(self::FirstId);
		$unused = BinaryId::fromHex(self::UnusedId);

		$single = $queries->existsManyBy(InvestmentAccount::class, [['portfolio' => $first->bytes], ['portfolio' => $unused->bytes]]);
		$this->assertTrue($single->has(['portfolio' => $first->bytes]));
		$this->assertFalse($single->has(['portfolio' => $unused->bytes]));

		$multiple = $queries->existsManyBy(InvestmentAccount::class, [['id' => 1, 'portfolio' => $first->bytes], ['id' => 3, 'portfolio' => $first->bytes]]);
		$this->assertTrue($multiple->has(['id' => 1, 'portfolio' => $first->bytes]));
		$this->assertFalse($multiple->has(['id' => 3, 'portfolio' => $first->bytes]));
	}

	private function persistPortfolios(): void
	{
		$em = $this->getEntityManager();
		$em->persist($first = new Portfolio(BinaryId::fromHex(self::FirstId), 'First'));
		$em->persist($second = new Portfolio(BinaryId::fromHex(self::SecondId), 'Second'));
		$em->persist(new Portfolio(BinaryId::fromHex(self::EmptyId), 'Empty'));
		$em->persist(new InvestmentAccount(1, $first, new AccountNumber(1001)));
		$em->persist(new InvestmentAccount(2, $first, new AccountNumber(1002)));
		$em->persist(new InvestmentAccount(3, $second, new AccountNumber(2001)));
		$em->flush();
		$em->clear();
	}

	/**
	 * @param array<array-key, mixed> $criteria
	 * @return list<int>
	 */
	private function findAccountIds(string $queryType, array $criteria): array
	{
		$queries = $this->createDoctrineQueries();
		$orderBy = ['id' => SortDirection::Ascending];

		return match ($queryType) {
			'objects' => array_map(
				static fn (InvestmentAccount $account): int => $account->getId(),
				$queries->objects->findBy(InvestmentAccount::class, $criteria, $orderBy)->asArray(),
			),
			'arrays' => $this->extractIds($queries->arrays->findBy(InvestmentAccount::class, $criteria, $orderBy)->asArray()),
			'scalars' => $this->extractIds($queries->scalars->findBy(InvestmentAccount::class, $criteria, $orderBy)->asArray()),
		};
	}

	/**
	 * @param array<array-key, mixed> $criteria
	 * @return list<mixed>
	 */
	private function findPortfolioNames(array $criteria): array
	{
		return $this->createDoctrineQueries()->scalars
			->findColumnValuesBy(Portfolio::class, 'name', $criteria, ['name' => SortDirection::Ascending])
			->asArray();
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<int>
	 */
	private function extractIds(array $rows): array
	{
		return array_map(static function (array $row): int {
			self::assertIsInt($row['id']);

			return $row['id'];
		}, $rows);
	}

}
