<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Types;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Query\RawQueryBuilder;
use Shredio\DoctrineQueries\Result\DatabaseRawResults;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\Enum\Priority;
use Tests\TestCase;

final class RawQueryBuilderTest extends TestCase
{

	use DoctrineContext;

	/**
	 * The value SQLite returns tells which type was bound, e.g. a string bound value comes back as a string.
	 *
	 * @return iterable<string, array{mixed, mixed}>
	 */
	public static function provideDetectedTypes(): iterable
	{
		yield 'integer' => [42, 42];
		yield 'string' => ['42', '42'];
		yield 'float is bound as a string' => [1.5, '1.5'];
		yield 'null' => [null, null];
		yield 'true' => [true, 1];
		yield 'false' => [false, 0];
		yield 'immutable datetime' => [new DateTimeImmutable('2021-01-02 03:04:05'), '2021-01-02 03:04:05'];
		yield 'mutable datetime' => [new DateTime('2021-01-02 03:04:05'), '2021-01-02 03:04:05'];
		yield 'date interval' => [new DateInterval('P1DT2H'), '+P00Y00M01DT02H00M00S'];
		yield 'string backed enum' => [ArticleType::News, 'news'];
		yield 'int backed enum' => [Priority::High, 2];
	}

	#[DataProvider('provideDetectedTypes')]
	public function testDetectedType(mixed $value, mixed $expected): void
	{
		$rows = $this->createRawQuery('SELECT :value AS v')->setParameter('value', $value)->getResult()->asArray();

		$this->assertSame([['v' => $expected]], $rows);
	}

	/**
	 * @return iterable<string, array{list<mixed>, list<int|string>}>
	 */
	public static function provideDetectedArrayTypes(): iterable
	{
		yield 'integers' => [[1, 3], [1, 3]];
		yield 'strings' => [['Sample Article', 'Third Article'], [1, 3]];
		yield 'empty list' => [[], []];
		yield 'string backed enums' => [[ArticleType::News], [1, 2, 3]];
		yield 'int backed enums' => [[Priority::Low, Priority::High], [1, 2]];
	}

	/**
	 * @param list<mixed> $values
	 * @param list<int> $expectedIds
	 */
	#[DataProvider('provideDetectedArrayTypes')]
	public function testDetectedArrayType(array $values, array $expectedIds): void
	{
		$this->persistFixtures();

		$column = match (true) {
			($values[0] ?? null) instanceof ArticleType => 'type',
			is_string($values[0] ?? null) => 'title',
			default => 'id',
		};

		$rows = $this->createRawQuery(sprintf('SELECT id FROM Article WHERE %s IN (:values) ORDER BY id', $column))
			->setParameter('values', $values)
			->getResult()
			->asArray();

		$this->assertSame($expectedIds, array_column($rows, 'id'));
	}

	public function testExplicitTypeOverridesDetection(): void
	{
		$query = $this->createRawQuery('SELECT :integer AS integer_value, :string AS string_value, :date AS date_value, :list AS list_value');

		$rows = $query
			->setParameter('integer', '42', ParameterType::INTEGER)
			->setParameter('string', 42, ParameterType::STRING)
			->setParameter('date', new DateTimeImmutable('2021-01-02 03:04:05'), Types::DATE_IMMUTABLE)
			->setParameter('list', ['5'], ArrayParameterType::INTEGER)
			->getResult()
			->asArray();

		$this->assertSame([['integer_value' => 42, 'string_value' => '42', 'date_value' => '2021-01-02', 'list_value' => 5]], $rows);
	}

	public function testCreateQueryFromFile(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(
			[['id' => 1, 'title' => 'Sample Article', 'author_name' => 'John Doe']],
			$queries->createQueryFromFile(Article::class, __DIR__ . '/test_query.sql')->setParameter('articleId', 1)->getResult()->asArray(),
		);
		$this->assertSame(
			[['total_count' => 2]],
			$queries->createQueryFromFile(Article::class, __DIR__ . '/count_articles.sql')->setParameter('authorId', 1)->getResult()->asArray(),
		);
		$this->assertSame(
			[['id' => 1, 'title' => 'Sample Article', 'author_name' => 'John Doe'], ['id' => 3, 'title' => 'Third Article', 'author_name' => 'John Doe']],
			$queries->createQueryFromFile(Article::class, __DIR__ . '/test_in_query.sql')->setParameter('articleIds', [1, 3])->getResult()->asArray(),
		);
	}

	public function testCreateQueryFromMissingFile(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage("File '/non/existent.sql' does not exist or is not a file.");

		$this->createDoctrineQueries()->createQueryFromFile(Article::class, '/non/existent.sql');
	}

	public function testCreateQueryFromDirectory(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('does not exist or is not a file.');

		$this->createDoctrineQueries()->createQueryFromFile(Article::class, __DIR__);
	}

	public function testResultsAsListAndAssociative(): void
	{
		$this->persistFixtures();
		$sql = 'SELECT id, title FROM Article WHERE id < 3 ORDER BY id';

		$this->assertSame([['id' => 1, 'title' => 'Sample Article'], ['id' => 2, 'title' => 'Another Article']], $this->createRawQuery($sql)->getResult()->asArray());
		$this->assertSame([[1, 'Sample Article'], [2, 'Another Article']], $this->createRawQuery($sql)->getResult()->asList());
		$this->assertSame([[1, 'Sample Article'], [2, 'Another Article']], iterator_to_array($this->createRawQuery($sql)->getResult()->yieldAsList()));
	}

	public function testYieldFreesTheResultOnlyWhenAsked(): void
	{
		foreach ([true, false] as $closeCursor) {
			$driverResult = $this->createMock(DriverResult::class);
			$driverResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(['id' => 1], false);
			$driverResult->expects($closeCursor ? $this->once() : $this->never())->method('free');

			$results = new DatabaseRawResults(new Result($driverResult, $this->getEntityManager()->getConnection()));

			$this->assertSame([['id' => 1]], iterator_to_array($results->yield($closeCursor)));
		}

		foreach ([true, false] as $closeCursor) {
			$driverResult = $this->createMock(DriverResult::class);
			$driverResult->method('fetchNumeric')->willReturnOnConsecutiveCalls([1], false);
			$driverResult->expects($closeCursor ? $this->once() : $this->never())->method('free');

			$results = new DatabaseRawResults(new Result($driverResult, $this->getEntityManager()->getConnection()));

			$this->assertSame([[1]], iterator_to_array($results->yieldAsList($closeCursor)));
		}
	}

	public function testResultsAreFreedByDefault(): void
	{
		$createResults = function (string $fetchMethod): DatabaseRawResults {
			$driverResult = $this->createMock(DriverResult::class);
			$driverResult->method($fetchMethod)->willReturnOnConsecutiveCalls(['id' => 1], false);
			$driverResult->expects($this->once())->method('free');

			return new DatabaseRawResults(new Result($driverResult, $this->getEntityManager()->getConnection()));
		};

		iterator_to_array($createResults('fetchAssociative')->yield());
		iterator_to_array($createResults('fetchNumeric')->yieldAsList());
		$createResults('fetchAssociative')->asArray();
		$createResults('fetchNumeric')->asList();
	}

	private function createRawQuery(string $sql): RawQueryBuilder
	{
		return new RawQueryBuilder($sql, $this->getEntityManager()->getConnection());
	}

}
