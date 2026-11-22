<?php declare(strict_types = 1);

namespace Tests\Context;

use DateTimeImmutable;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\After;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Query\SimplifiedQueryBuilderFactory;
use Shredio\DoctrineQueries\Select\QueryType;
use Tests\Doctrine\QueryCounter;
use Tests\Doctrine\Symbol;
use Tests\Doctrine\TestManagerRegistry;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\Entity\Role;
use Tests\Factory\EntityManagerFactory;

/**
 * Fixtures created by persistFixtures():
 *
 * | Article | title           | author         | author role   | symbol |
 * |---------|-----------------|----------------|---------------|--------|
 * | 1       | Sample Article  | 1 (John Doe)   | -             | sym    |
 * | 2       | Another Article | 2 (Jane Smith) | Administrator | null   |
 * | 3       | Third Article   | 1 (John Doe)   | -             | null   |
 */
trait DoctrineContext
{

	private const string FixtureCreatedAt = '2021-01-01 00:00:00';

	private ?EntityManagerInterface $em = null;

	private ?QueryCounter $queryCounter = null;

	private function getEntityManager(): EntityManagerInterface
	{
		if ($this->em !== null) {
			return $this->em;
		}

		$this->em = $em = EntityManagerFactory::create([
			new Middleware($this->queryCounter = new QueryCounter()),
		]);

		if ($this->setUpDatabase()) {
			$schemaTool = new SchemaTool($em);
			if (!EntityManagerFactory::isInMemoryDatabase()) {
				$schemaTool->dropDatabase(); // a real database keeps the tables of the previous test
			}

			$schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
		}

		return $em;
	}

	/**
	 * @return callable(): list<string>
	 */
	private function captureQueries(): callable
	{
		$this->getEntityManager();
		if ($this->queryCounter === null) {
			throw new \LogicException('Query counter is not initialized.');
		}

		return $this->queryCounter->capture();
	}

	/**
	 * @return callable(): int
	 */
	private function captureQueryCount(): callable
	{
		$getQueries = $this->captureQueries();

		return static fn (): int => count($getQueries());
	}

	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	private function assertQueryCount(int $expectedCount, callable $callback, string $message = ''): mixed
	{
		$getQueries = $this->captureQueries();
		$result = $callback();
		$queries = $getQueries();

		$this->assertCount(
			$expectedCount,
			$queries,
			$message !== '' ? $message : sprintf("Expected %d queries, executed:\n%s", $expectedCount, implode("\n", $queries)),
		);

		return $result;
	}

	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	private function assertSingleQuery(callable $callback): mixed
	{
		return $this->assertQueryCount(1, $callback);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 */
	private function getMetadata(string $className, QueryType $queryType): QueryMetadata
	{
		return new QueryMetadata($this->getEntityManager()->getMetadataFactory(), $this->getEntityManager()->getClassMetadata($className), $queryType);
	}

	private function createManagerRegistry(): TestManagerRegistry
	{
		return new TestManagerRegistry($this->getEntityManager());
	}

	private function createQueryBuilderFactory(): SimplifiedQueryBuilderFactory
	{
		return new SimplifiedQueryBuilderFactory($this->createManagerRegistry());
	}

	private function createDoctrineQueries(): DoctrineQueries
	{
		return new DoctrineQueries($this->createManagerRegistry());
	}

	private static function createFixtureDate(): DateTimeImmutable
	{
		return new DateTimeImmutable(self::FixtureCreatedAt);
	}

	/**
	 * @param (callable(Author, Role, Author): (list<object>|object))|null $factory Receives John Doe, the Administrator role and Jane Smith.
	 */
	private function persistFixtures(?callable $factory = null): void
	{
		$em = $this->getEntityManager();
		$em->persist($admin = new Role(1, 'Administrator'));
		$em->persist($author = new Author(1, 'John Doe'));
		$em->persist($author2 = new Author(2, 'Jane Smith', $admin));
		$em->persist(new Article(1, 'Sample Article', 'This is a sample article.', $author, new Symbol('sym'), self::createFixtureDate()));
		$em->persist(new Article(2, 'Another Article', 'This is another article.', $author2, createdAt: self::createFixtureDate()));
		$em->persist(new Article(3, 'Third Article', 'This is the third article.', $author, createdAt: self::createFixtureDate()));

		if ($factory !== null) {
			$entities = $factory($author, $admin, $author2);
			foreach (is_array($entities) ? $entities : [$entities] as $entity) {
				$em->persist($entity);
			}
		}

		$em->flush();
		$em->clear();
	}

	#[After]
	protected function tearDownDoctrine(): void
	{
		if ($this->em !== null) {
			$this->em->close();
			$this->em->getConnection()->close(); // a real database would run out of connections otherwise
			$this->em = null;
			$this->queryCounter = null;
		}
	}

	protected function setUpDatabase(): bool
	{
		return true;
	}

}
