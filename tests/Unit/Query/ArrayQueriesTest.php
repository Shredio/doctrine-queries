<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use DateTimeImmutable;
use Shredio\DoctrineQueries\Pagination\Pagination;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\Symbol;
use Tests\Entity\Article;
use Tests\Entity\Enum\ArticleType;
use Tests\TestCase;
use Tests\Unit\Helpers;

final class ArrayQueriesTest extends TestCase
{

	use ClockSensitiveTrait;
	use DoctrineContext;
	use Helpers;

	public function testFindBy(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class)->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));

		$this->assertNotEmpty(array_column($values, 'createdAt'));
		foreach (array_column($values, 'createdAt') as $value) {
			$this->assertInstanceOf(DateTimeImmutable::class, $value);
		}

		$this->assertNotEmpty($columns = array_column($values, 'symbol'));
		$this->assertInstanceOf(Symbol::class, $columns[0]);
		$this->assertNull($columns[1]);
		$this->assertNull($columns[2]);
	}

	public function testFindByWithJoins(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, criteria: ['author.name' => 'Jane Smith'])->asArray();

		$this->assertSame([
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'symbol' => null,
				'type' => ArticleType::News,
			],
		], $this->unsetColumns($values, ['createdAt']));
	}

	public function testFindByWithSelectJoins(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, select: ['id', 'author.name'], orderBy: ['id' => 'ASC'])->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'name' => 'John Doe',
			],
			[
				'id' => 2,
				'name' => 'Jane Smith',
			],
			[
				'id' => 3,
				'name' => 'John Doe',
			],
		], $this->unsetColumns($values, ['createdAt']));
	}

	public function testFindByWithOrderByJoins(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, orderBy: ['author.name' => 'ASC'], select: ['id', 'author.name'])->asArray();

		$this->assertSame([
			[
				'id' => 2,
				'name' => 'Jane Smith',
			],
			[
				'id' => 1,
				'name' => 'John Doe',
			],
			[
				'id' => 3,
				'name' => 'John Doe',
			],
		], $this->unsetColumns($values, ['createdAt']));
	}

	public function testFindByWithRelationSelection(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, select: ['**'])->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
				'author' => 1,
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'author' => 2,
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'author' => 1,
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));

		$this->assertNotEmpty(array_column($values, 'createdAt'));
		foreach (array_column($values, 'createdAt') as $value) {
			$this->assertInstanceOf(DateTimeImmutable::class, $value);
		}

		$this->assertNotEmpty($columns = array_column($values, 'symbol'));
		$this->assertInstanceOf(Symbol::class, $columns[0]);
		$this->assertNull($columns[1]);
		$this->assertNull($columns[2]);

		$this->assertHasAllFields($values, Article::AllFields);
	}

	public function testFindByWithRelationWildcardSelection(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, select: ['author.*'])->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'name' => 'John Doe',
			],
			[
				'id' => 1,
				'name' => 'John Doe',
			],
			[
				'id' => 2,
				'name' => 'Jane Smith',
			],
		], $values);
	}

	public function testColumnValuesNullableRelations(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'author.role.id', joinConfig: ['author.role' => 'left'])->asArray();

		$this->assertSame([
			null, null, 1,
		], $values);
	}

	public function testColumnValuesNonNullableRelations(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'author.role.id', joinConfig: 'inner')->asArray();

		$this->assertSame([
			1,
		], $values);
	}

	public function testColumnValuesNonNullableRelations2(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'author.role.id', joinConfig: ['author.role' => 'inner'])->asArray();

		$this->assertSame([
			1,
		], $values);
	}

	public function testColumnValuesDistinctNullableRelations(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'author.role.id', distinct: true, joinConfig: ['author.role' => 'left'])->asArray();

		$this->assertSame([
			null, 1,
		], $values);
	}

	public function testFindByRelationWithWildcardRelationSelection(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, select: ['author.**'])->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'name' => 'John Doe',
				'role' => null,
			],
			[
				'id' => 1,
				'name' => 'John Doe',
				'role' => null,
			],
			[
				'id' => 2,
				'name' => 'Jane Smith',
				'role' => 1,
			],
		], $values);
	}

	public function testRelationPrefixFindByRelationWithWildcardRelationSelection(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, select: ['*', 'author.**' => 'author_'])->asArray();

		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
				'author_id' => 1,
				'author_name' => 'John Doe',
				'author_role' => null,
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
				'author_id' => 2,
				'author_name' => 'Jane Smith',
				'author_role' => 1,
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
				'author_id' => 1,
				'author_name' => 'John Doe',
				'author_role' => null,
			],
		], $this->unsetColumns($values, $keys = ['createdAt', 'symbol', 'type']));

		$this->assertValuesHasKeys($keys, $values);
	}

	public function testFindIndexedByYield(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = iterator_to_array($queries->findIndexedBy(Article::class, 'title')->yield());

		$this->assertSame([
			'Sample Article' => [
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
			],
			'Another Article' => [
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
			],
			'Third Article' => [
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));
	}

	public function testFindIndexedBy(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findIndexedBy(Article::class, 'title')->asArray();

		$this->assertSame([
			'Sample Article' => [
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
			],
			'Another Article' => [
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
			],
			'Third Article' => [
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));
	}

	public function testFindIndexedByUnsetIndexField(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findIndexedBy(Article::class, 'title', select: ['id'])->asArray();

		$this->assertSame([
			'Sample Article' => [
				'id' => 1,
			],
			'Another Article' => [
				'id' => 2,
			],
			'Third Article' => [
				'id' => 3,
			],
		], $values);
	}

	public function testFindOneBy(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$value = $queries->findOneBy(Article::class, ['id' => 1]);

		$this->assertSame([
			'id' => 1,
			'title' => 'Sample Article',
			'content' => 'This is a sample article.',
		], $this->unsetColumns([$value], ['createdAt', 'symbol', 'type'])[0]);

		$this->assertInstanceOf(DateTimeImmutable::class, $value['createdAt']);
	}

	public function testFindOneByReturnsNull(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$value = $queries->findOneBy(Article::class, ['id' => 999]);

		$this->assertNull($value);
	}

	public function testFindByWithPagination(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, pagination: new Pagination(2, 1))->asArray();

		$this->assertCount(2, $values);
		$this->assertSame([
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
			],
			[
				'id' => 3,
				'title' => 'Third Article',
				'content' => 'This is the third article.',
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));
	}

	public function testFindByWithPaginationLimitOnly(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, pagination: new Pagination(2))->asArray();

		$this->assertCount(2, $values);
		$this->assertSame([
			[
				'id' => 1,
				'title' => 'Sample Article',
				'content' => 'This is a sample article.',
			],
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));
	}

	public function testFindByWithPaginationAndCriteria(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findBy(Article::class, ['id >' => 1], pagination: new Pagination(1))->asArray();

		$this->assertCount(1, $values);
		$this->assertSame([
			[
				'id' => 2,
				'title' => 'Another Article',
				'content' => 'This is another article.',
			],
		], $this->unsetColumns($values, ['createdAt', 'symbol', 'type']));
	}

	public function testFindColumnValuesByWithPagination(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'title', pagination: new Pagination(2))->asArray();

		$this->assertCount(2, $values);
		$this->assertSame([
			'Sample Article',
			'Another Article',
		], $values);
	}

	public function testFindColumnValuesByWithPaginationOffset(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'title', pagination: new Pagination(2, 1))->asArray();

		$this->assertCount(2, $values);
		$this->assertSame([
			'Another Article',
			'Third Article',
		], $values);
	}

	public function testFindColumnValuesByWithPaginationAndCriteria(): void
	{
		self::mockTime(new DateTimeImmutable('2021-01-01 00:00:00'));

		$this->persistFixtures();
		$queries = $this->getQueries();
		$values = $queries->findColumnValuesBy(Article::class, 'title', ['id >' => 1], pagination: new Pagination(1))->asArray();

		$this->assertCount(1, $values);
		$this->assertSame([
			'Another Article',
		], $values);
	}

	private function getQueries(): ArrayQueries
	{
		return new ArrayQueries(new SimplifiedQueryBuilderFactory($this->createManagerRegistry()));
	}

	private function assertHasAllFields(array $values, array $fields): void
	{
		sort($fields);

		foreach ($values as $item) {
			$keys = array_keys($item);
			sort($keys);

			$this->assertSame($fields, $keys);
		}
	}

	/**
	 * @param list<string> $keys
	 * @param mixed[] $values
	 */
	private function assertValuesHasKeys(array $keys, array $values): void
	{
		foreach ($values as $value) {
			$this->assertIsArray($value);
			foreach ($keys as $key) {
				$this->assertArrayHasKey($key, $value);
			}
		}
	}

}
