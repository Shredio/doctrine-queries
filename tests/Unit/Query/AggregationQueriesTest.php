<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
use Doctrine\ORM\Tools\SchemaTool;
use PDO;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Exception\AggregationOverflowException;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidResultKeyException;
use Shredio\DoctrineQueries\Exception\MultipleFieldSelectionException;
use Shredio\DoctrineQueries\Exception\ToManyAssociationException;
use Shredio\DoctrineQueries\Exception\UnsupportedAggregationException;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\Symbol;
use Tests\Doctrine\TestManagerRegistry;
use Tests\Entity\Article;
use Tests\Entity\ArticleHashtag;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\Enum\Priority;
use Tests\Entity\Note;
use Tests\Entity\NoteTag;
use Tests\Entity\TypedValues;
use Tests\Entity\UsageRecord;
use Tests\Factory\EntityManagerFactory;
use Tests\TestCase;
use Tests\Unit\Helpers;

/**
 * sumBy(), minBy(), maxBy() and their grouped variants of DoctrineQueries.
 *
 * Typed values created by persistTypedValues():
 *
 * | id | label | small | price | ratio | day        | kind | priority | nullableNumber |
 * |----|-------|-------|-------|-------|------------|------|----------|----------------|
 * | 1  | a     | 7     | 12.50 | 0.5   | 2021-01-02 | news | High     | 5              |
 * | 2  | a     | -3    | 0.25  | 0.25  | 2021-03-04 | blog | Low      | null           |
 * | 3  | b     | 10    | 99.75 | 1.0   | 2020-12-31 | news | High     | null           |
 */
final class AggregationQueriesTest extends TestCase
{

	use DoctrineContext;
	use Helpers;

	public function testSumByReturnsTheKindOfTheField(): void
	{
		$this->persistTypedValues();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(14, $this->assertSingleQuery(static fn (): mixed => $queries->sumBy(TypedValues::class, 'small')));
		$this->assertSame(6, $queries->sumBy(TypedValues::class, 'id'));
		$this->assertSame(3, $queries->sumBy(TypedValues::class, 'big'), 'A bigint is summed to an integer too.');
		$this->assertSame('112.50', self::withTwoDecimals($queries->sumBy(TypedValues::class, 'price')), 'A decimal is summed to a numeric string.');
		$this->assertSame(1.75, $queries->sumBy(TypedValues::class, 'ratio'));
		$this->assertSame(5, $queries->sumBy(TypedValues::class, 'priority'), 'An enum is summed by its values.');
	}

	public function testSumByCriteria(): void
	{
		$this->persistTypedValues();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(4, $queries->sumBy(TypedValues::class, 'small', ['label' => 'a']));
		$this->assertSame(17, $queries->sumBy(TypedValues::class, 'small', ['small >' => 0, 'kind' => ArticleType::News]));
	}

	public function testSumByIsNullWithoutValues(): void
	{
		$this->persistTypedValues();
		$queries = $this->createDoctrineQueries();

		$this->assertNull($queries->sumBy(TypedValues::class, 'small', ['id' => 0]), 'No entity matches.');
		$this->assertNull($queries->sumBy(TypedValues::class, 'nullableNumber', ['id' => [2, 3]]), 'All the values are NULL.');
		$this->assertSame(5, $queries->sumBy(TypedValues::class, 'nullableNumber'), 'NULL values are skipped.');
		$this->assertNull($queries->sumBy(TypedValues::class, 'nullablePrice'));
		$this->assertNull($queries->sumBy(TypedValues::class, 'nullableRatio'));
	}

	public function testSumByRelatedField(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(4, $queries->sumBy(Article::class, 'author.id'), 'The authors 1, 2 and 1 of the three articles.');
		$this->assertSame(1, $queries->sumBy(Article::class, 'author.role.id'), 'Only Jane Smith has a role.');
		$this->assertSame(1, $queries->sumBy(Article::class, 'author.role.id', joinConfig: 'inner'));
	}

	public function testCriteriaOnToManyAssociationDoNotMultiplyTheSum(): void
	{
		$this->persistFixtures();
		$this->persistHashtags();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			3,
			$queries->sumBy(Article::class, 'id', ['hashTags.hashtag' => ['php', 'doctrine']]),
			'The first article matches two hashtags and is summed once.',
		);
	}

	public function testSumByFieldThroughToManyAssociation(): void
	{
		$em = $this->getEntityManager();
		$em->persist($php = new NoteTag(1, 'php'));
		$em->persist($doctrine = new NoteTag(2, 'doctrine'));
		$em->persist(new Note(1, 'First', [$php, $doctrine]));
		$em->persist(new Note(2, 'Second', [$php]));
		$em->persist(new Note(3, 'Third'));
		$em->flush();
		$em->clear();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(4, $queries->sumBy(Note::class, 'tags.id'), 'The tags 1 and 2 of the first note and 1 of the second one.');
		$this->assertSame(2, $queries->sumBy(Note::class, 'tags.id', ['tags.name' => 'php']), 'Criteria on the association filter the summed tags.');
		$this->assertSame(
			[1 => 3, 2 => 1],
			self::sortedByKey($queries->sumGroupedBy(Note::class, 'tags.id', 'id', ['tags.id !=' => null])->asArray()),
		);
		$this->assertSame(
			['doctrine' => 1, 'php' => 2],
			self::sortedByKey($queries->maxGroupedBy(Note::class, 'id', 'tags.name', joinConfig: 'inner')->asArray()),
			'The highest identifier of the notes with the tag.',
		);
	}

	public function testSumByOverflow(): void
	{
		if (EntityManagerFactory::isInMemoryDatabase()) {
			$this->markTestSkipped('SQLite fails the query with "integer overflow" itself.');
		}

		$em = $this->getEntityManager();
		$em->persist(self::createTypedValues(1));
		$em->persist(self::createTypedValues(2));
		$em->flush();
		$em->createQuery(sprintf('UPDATE %s t SET t.big = :big', TypedValues::class))->execute(['big' => PHP_INT_MAX]);

		$this->expectException(AggregationOverflowException::class);
		$this->expectExceptionMessage('SUM() of Tests\Entity\TypedValues::$big returned \'18446744073709551614\', which is not an integer PHP can represent.');

		$this->createDoctrineQueries()->sumBy(TypedValues::class, 'big');
	}

	public function testMinByAndMaxByConvertTheValueByTheFieldType(): void
	{
		$this->persistTypedValues();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(-3, $this->assertSingleQuery(static fn (): mixed => $queries->minBy(TypedValues::class, 'small')));
		$this->assertSame(10, $queries->maxBy(TypedValues::class, 'small'));
		$this->assertSame('0.25', $queries->minBy(TypedValues::class, 'price'));
		$this->assertSame('99.75', $queries->maxBy(TypedValues::class, 'price'));
		$this->assertSame(0.25, $queries->minBy(TypedValues::class, 'ratio'));
		$this->assertSame('a', $queries->minBy(TypedValues::class, 'label'));
		$this->assertSame('b', $queries->maxBy(TypedValues::class, 'label'));
		$this->assertTrue($queries->maxBy(TypedValues::class, 'flag'));
		$this->assertSame('DateTimeImmutable(2020-12-31 00:00:00)', self::describeObjects($queries->minBy(TypedValues::class, 'day')));
		$this->assertSame('DateTimeImmutable(2021-03-04 03:04:05)', self::describeObjects($queries->maxBy(TypedValues::class, 'moment')));
		$this->assertSame(ArticleType::Blog, $queries->minBy(TypedValues::class, 'kind'), 'An enum is compared by its value.');
		$this->assertSame(Priority::High, $queries->maxBy(TypedValues::class, 'priority'));
	}

	public function testMinByAndMaxByOfCustomType(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame('Symbol(sym)', self::describeObjects($queries->maxBy(Article::class, 'symbol')));
		$this->assertSame('Symbol(sym)', self::describeObjects($queries->minBy(Article::class, 'symbol')), 'NULL values are skipped.');
		$this->assertSame('Jane Smith', $queries->minBy(Article::class, 'author.name'));
		$this->assertSame('Administrator', $queries->maxBy(Article::class, 'author.role.name'));
	}

	public function testMinByAndMaxByAreNullWithoutValues(): void
	{
		$this->persistTypedValues();
		$queries = $this->createDoctrineQueries();

		$this->assertNull($queries->minBy(TypedValues::class, 'small', ['id' => 0]));
		$this->assertNull($queries->maxBy(TypedValues::class, 'day', ['id' => 0]));
		$this->assertNull($queries->maxBy(TypedValues::class, 'nullableMoment'));
		$this->assertSame(5, $queries->minBy(TypedValues::class, 'nullableNumber'), 'NULL values are skipped.');
		$this->assertSame(7, $queries->maxBy(TypedValues::class, 'small', ['label' => 'a']));
	}

	public function testSumGroupedBy(): void
	{
		$this->persistTypedValues();
		$queries = $this->createDoctrineQueries();

		$sums = $this->assertSingleQuery(static fn (): array => $queries->sumGroupedBy(TypedValues::class, 'small', 'label')->asArray());

		$this->assertSame(['a' => 4, 'b' => 10], self::sortedByKey($sums));
		$this->assertSame(
			['a' => '12.75', 'b' => '99.75'],
			self::sortedByKey($queries->sumGroupedBy(TypedValues::class, 'price', 'label')->asArray()),
		);
		$this->assertSame(['a' => 0.75, 'b' => 1.0], self::sortedByKey($queries->sumGroupedBy(TypedValues::class, 'ratio', 'label')->asArray()));
		$this->assertSame(
			['a' => 5, 'b' => null],
			self::sortedByKey($queries->sumGroupedBy(TypedValues::class, 'nullableNumber', 'label')->asArray()),
			'The sum of a group with NULL values only is null.',
		);
		$this->assertSame(['a' => 7], $queries->sumGroupedBy(TypedValues::class, 'small', 'label', ['small >' => 0, 'label' => ['a', 'missing']])->asArray());
		$this->assertSame([], $queries->sumGroupedBy(TypedValues::class, 'small', 'label', ['id' => 0])->asArray());
	}

	public function testMinGroupedByAndMaxGroupedBy(): void
	{
		$this->persistTypedValues();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(['a' => -3, 'b' => 10], self::sortedByKey($queries->minGroupedBy(TypedValues::class, 'small', 'label')->asArray()));
		$this->assertSame(['a' => 7, 'b' => 10], self::sortedByKey($queries->maxGroupedBy(TypedValues::class, 'small', 'label')->asArray()));
		$this->assertSame(
			['a' => 'DateTimeImmutable(2021-03-04 00:00:00)', 'b' => 'DateTimeImmutable(2020-12-31 00:00:00)'],
			self::sortedByKey(self::describeObjects($queries->maxGroupedBy(TypedValues::class, 'day', 'label')->asArray())),
			'The values are converted by the type of the field.',
		);
		$this->assertSame(
			['blog' => Priority::Low, 'news' => Priority::High],
			self::sortedByKey($queries->minGroupedBy(TypedValues::class, 'priority', 'kind')->asArray()),
			'The keys are database values, the values are converted.',
		);
		$this->assertSame(
			['a' => 5, 'b' => null],
			self::sortedByKey($queries->maxGroupedBy(TypedValues::class, 'nullableNumber', 'label')->asArray()),
		);
		$this->assertSame(
			['2020-12-31' => 3, '2021-01-02' => 1, '2021-03-04' => 2],
			self::sortedByKey($queries->maxGroupedBy(TypedValues::class, 'id', 'day')->asArray()),
			'A date key is the database value.',
		);
	}

	public function testGroupedByAssociation(): void
	{
		$this->persistFixtures();
		$this->persistHashtags();
		$queries = $this->createDoctrineQueries();

		$this->assertSame([1 => 3, 2 => 2], self::sortedByKey($queries->maxGroupedBy(Article::class, 'id', 'author')->asArray()));
		$this->assertSame([1 => 4, 2 => 2], self::sortedByKey($queries->sumGroupedBy(Article::class, 'id', 'author')->asArray()));
		$this->assertSame([2 => 2], $queries->sumGroupedBy(Article::class, 'id', 'author', ['author' => [2, 999]])->asArray());
		$this->assertSame(
			['Administrator' => 2],
			$queries->minGroupedBy(Article::class, 'id', 'author.role.name', joinConfig: 'inner')->asArray(),
		);
		$this->assertSame(
			['doctrine' => 1, 'php' => 3, 'symfony' => 3],
			self::sortedByKey($queries->sumGroupedBy(Article::class, 'id', 'hashTags.hashtag')->asArray()),
			'The articles 1 and 2 have the hashtag php.',
		);
		$this->assertSame(
			[1 => 4, 2 => 2],
			self::sortedByKey($queries->sumGroupedBy(Article::class, 'id', 'author', ['hashTags.hashtag !=' => 'unknown'])->asArray()),
			'Criteria on a to-many association do not multiply the sums.',
		);
	}

	public function testGroupedNullKey(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$pairs = [];
		foreach ($queries->sumGroupedBy(Article::class, 'id', 'symbol')->yield() as $symbol => $sum) {
			$pairs[] = [$symbol, $sum];
		}
		usort($pairs, static fn (array $first, array $second): int => $first[1] <=> $second[1]);

		$this->assertSame([['sym', 1], [null, 5]], $pairs);
		$this->assertSame(['sym' => 1], $queries->sumGroupedBy(Article::class, 'id', 'symbol', ['symbol !=' => null])->asArray());

		$this->expectException(InvalidResultKeyException::class);

		$queries->sumGroupedBy(Article::class, 'id', 'symbol')->asArray();
	}

	public function testStringifiedFetches(): void
	{
		$em = EntityManagerFactory::createInMemory([PDO::ATTR_STRINGIFY_FETCHES => true]);
		(new SchemaTool($em))->createSchema([$em->getClassMetadata(TypedValues::class)]);
		foreach (self::createAllTypedValues() as $typedValues) {
			$em->persist($typedValues);
		}
		$em->flush();

		$queries = new DoctrineQueries(new TestManagerRegistry($em));

		$this->assertSame(['7'], $queries->scalars->findColumnValuesBy(TypedValues::class, 'small', ['id' => 1])->asArray(), 'The driver returns integers as strings.');
		$this->assertSame(14, $queries->sumBy(TypedValues::class, 'small'));
		$this->assertSame('112.50', self::withTwoDecimals($queries->sumBy(TypedValues::class, 'price')));
		$this->assertSame(1.75, $queries->sumBy(TypedValues::class, 'ratio'));
		$this->assertSame(10, $queries->maxBy(TypedValues::class, 'small'));
		$this->assertSame(Priority::Low, $queries->minBy(TypedValues::class, 'priority'));
		$this->assertSame(['a' => 4, 'b' => 10], self::sortedByKey($queries->sumGroupedBy(TypedValues::class, 'small', 'label')->asArray()));

		$em->getConnection()->close();
	}

	public function testSumByRejectsFieldThatIsNotANumber(): void
	{
		$this->persistFixtures();

		$this->expectException(UnsupportedAggregationException::class);
		$this->expectExceptionMessage('SUM() of Tests\Entity\Article::$title is not supported, the field has the type "string". Only integer, decimal and float fields and fields of a custom type implementing Shredio\DoctrineQueries\Aggregation\SummableType can be summed.');

		$this->createDoctrineQueries()->sumBy(Article::class, 'title');
	}

	public function testSumOfSummableTypeIsConvertedByTheType(): void
	{
		$this->persistUsageRecords();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(4.0, $this->assertSingleQuery(static fn (): mixed => $queries->sumBy(UsageRecord::class, 'cost')), 'A float as the field, not a decimal string.');
		$this->assertSame(1.75, $queries->sumBy(UsageRecord::class, 'cost', ['provider' => 'a']));
		$this->assertSame(0.5, $queries->sumBy(UsageRecord::class, 'discount'), 'NULL values are skipped.');
		$this->assertSame(1.5, $queries->sumBy(UsageRecord::class, 'previous.cost'), 'The cost of the first record, the second one follows it.');
		$this->assertSame(2.25, $queries->maxBy(UsageRecord::class, 'cost'));
	}

	public function testSumOfSummableTypeIsNullWithoutValues(): void
	{
		$this->persistUsageRecords();
		$queries = $this->createDoctrineQueries();

		$this->assertNull($queries->sumBy(UsageRecord::class, 'cost', ['id' => 0]), 'No entity matches.');
		$this->assertNull($queries->sumBy(UsageRecord::class, 'discount', ['provider' => 'b']), 'All the values are NULL.');
	}

	public function testSumGroupedByOfSummableType(): void
	{
		$this->persistUsageRecords();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			['a' => 1.75, 'b' => 2.25],
			self::sortedByKey($this->assertSingleQuery(static fn (): array => $queries->sumGroupedBy(UsageRecord::class, 'cost', 'provider')->asArray())),
		);
		$this->assertSame(
			['a' => 0.5, 'b' => null],
			self::sortedByKey($queries->sumGroupedBy(UsageRecord::class, 'discount', 'provider')->asArray()),
			'The sum of a group is null when all its values are NULL.',
		);
		$this->assertSame([], $queries->sumGroupedBy(UsageRecord::class, 'cost', 'provider', ['id' => 0])->asArray());
	}

	public function testAggregationsOfUnsignedColumns(): void
	{
		$this->persistUsageRecords();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(60, $queries->sumBy(UsageRecord::class, 'tokens'));
		$this->assertSame(-60, $queries->sumBy(UsageRecord::class, 'balance'), 'A signed column next to the unsigned ones.');
		$this->assertSame(10, $queries->minBy(UsageRecord::class, 'tokens'));
		$this->assertSame(2, $queries->maxBy(UsageRecord::class, 'retries'));
		$this->assertSame(['a' => 30, 'b' => 30], self::sortedByKey($queries->sumGroupedBy(UsageRecord::class, 'tokens', 'provider')->asArray()));
	}

	public function testSumOfCustomTypeThatIsNotSummableIsRejected(): void
	{
		$this->persistUsageRecords();

		$this->expectException(UnsupportedAggregationException::class);
		$this->expectExceptionMessage('SUM() of Tests\Entity\UsageRecord::$account is not supported, the field has the type "account_number".');

		$this->createDoctrineQueries()->sumBy(UsageRecord::class, 'account');
	}

	public function testSumGroupedByRejectsFieldThatIsNotANumber(): void
	{
		$this->persistFixtures();

		$this->expectException(UnsupportedAggregationException::class);

		$this->createDoctrineQueries()->sumGroupedBy(Article::class, 'createdAt', 'author');
	}

	public function testRejectsAssociation(): void
	{
		$this->persistFixtures();

		$this->expectException(UnsupportedAggregationException::class);
		$this->expectExceptionMessage('MAX() of Tests\Entity\Article::$author is not supported, "author" is an association. Aggregate a field of the related entity instead, e.g. "author.<field>".');

		$this->createDoctrineQueries()->maxBy(Article::class, 'author');
	}

	public function testRejectsToManyAssociation(): void
	{
		$this->persistFixtures();

		$this->expectException(ToManyAssociationException::class);

		$this->createDoctrineQueries()->minBy(Article::class, 'hashTags');
	}

	public function testRejectsWildcard(): void
	{
		$this->persistFixtures();

		$this->expectException(MultipleFieldSelectionException::class);

		$this->createDoctrineQueries()->sumBy(Article::class, '*');
	}

	public function testRejectsUnknownField(): void
	{
		$this->persistFixtures();

		$this->expectException(FieldNotExistsException::class);

		$this->createDoctrineQueries()->minBy(Article::class, 'unknown');
	}

	public function testRejectsUnknownGroupKey(): void
	{
		$this->persistFixtures();

		$this->expectException(FieldNotExistsException::class);

		$this->createDoctrineQueries()->maxGroupedBy(Article::class, 'id', 'unknown');
	}

	public function testRejectsWildcardGroupKey(): void
	{
		$this->persistFixtures();

		$this->expectException(MultipleFieldSelectionException::class);

		$this->createDoctrineQueries()->sumGroupedBy(Article::class, 'id', 'author.*');
	}

	private function persistTypedValues(): void
	{
		$em = $this->getEntityManager();
		foreach (self::createAllTypedValues() as $typedValues) {
			$em->persist($typedValues);
		}
		$em->flush();
		$em->clear();
	}

	/**
	 * @return list<TypedValues>
	 */
	private static function createAllTypedValues(): array
	{
		$first = self::createTypedValues(1, '12.50', '2021-01-02');
		$first->label = 'a';
		$first->nullableNumber = 5;

		$second = self::createTypedValues(2, '0.25', '2021-03-04');
		$second->label = 'a';
		$second->small = -3;
		$second->ratio = 0.25;
		$second->kind = ArticleType::Blog;
		$second->priority = Priority::Low;

		$third = self::createTypedValues(3, '99.75', '2020-12-31');
		$third->label = 'b';
		$third->small = 10;
		$third->ratio = 1.0;

		return [$first, $second, $third];
	}

	/**
	 * | id | provider | tokens | balance | cost | discount | retries | previous |
	 * |----|----------|--------|---------|------|----------|---------|----------|
	 * | 1  | a        | 10     | -10     | 1.5  | 0.5      | 2       | -        |
	 * | 2  | a        | 20     | -20     | 0.25 | null     | null    | 1        |
	 * | 3  | b        | 30     | -30     | 2.25 | null     | 0       | -        |
	 */
	private function persistUsageRecords(): void
	{
		$em = $this->getEntityManager();
		$em->persist($first = self::createUsageRecord(1, 'a', 10, 1.5, discount: 0.5, retries: 2));
		$em->persist(self::createUsageRecord(2, 'a', 20, 0.25, previous: $first));
		$em->persist(self::createUsageRecord(3, 'b', 30, 2.25, retries: 0));
		$em->flush();
		$em->clear();
	}

	private function persistHashtags(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'php'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'doctrine'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 2), 'php'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 3), 'symfony'));
		$em->flush();
		$em->clear();
	}

	/**
	 * SQLite has no decimal type and returns 112.5 for a sum MySQL returns as 112.50.
	 */
	private static function withTwoDecimals(mixed $value): string
	{
		self::assertIsString($value);
		self::assertIsNumeric($value);

		return number_format((float) $value, 2, '.', '');
	}

	/**
	 * The database returns the groups in no particular order.
	 *
	 * @template TValue
	 * @param array<array-key, TValue> $values
	 * @return array<array-key, TValue>
	 */
	private static function sortedByKey(array $values): array
	{
		ksort($values);

		return $values;
	}

}
