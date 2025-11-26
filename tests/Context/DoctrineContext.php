<?php declare(strict_types = 1);

namespace Tests\Context;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\After;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Select\QueryType;
use Tests\Doctrine\QueryCounter;
use Tests\Doctrine\Symbol;
use Tests\Doctrine\TestManagerRegistry;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\Entity\Role;
use Tests\Factory\EntityManagerFactory;

trait DoctrineContext
{

	private ?EntityManagerInterface $em = null;

	private ?QueryCounter $queryCounter = null;

	private function getEntityManager(): EntityManager
	{
		if ($this->em !== null) {
			return $this->em;
		}

		$this->em = $em = EntityManagerFactory::create([
			new \Doctrine\DBAL\Logging\Middleware($this->queryCounter = new QueryCounter()),
		]);

		if ($this->setUpDatabase()) {
			$schemaTool = new SchemaTool($em);
			$schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
		}

		return $em;
	}

	/**
	 * @return callable(): list<string>
	 */
	private function captureQueries(): callable
	{
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
		$fn = $this->queryCounter->capture();
		return fn (): int => count($fn());
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

	/**
	 * @param (callable(Author, Role): list<object>|object)|null $factory
	 */
	private function persistFixtures(?callable $factory = null): void
	{
		$em = $this->getEntityManager();
		$em->persist($admin = new Role(1, 'Administrator'));
		$em->persist($author = new Author(1, 'John Doe'));
		$em->persist($author2 = new Author(2, 'Jane Smith', $admin));
		$em->persist(new Article(1, 'Sample Article', 'This is a sample article.', $author, new Symbol('sym')));
		$em->persist(new Article(2, 'Another Article', 'This is another article.', $author2));
		$em->persist(new Article(3, 'Third Article', 'This is the third article.', $author));

		if ($factory) {
			$entities = $factory($author, $admin);
			if (is_array($entities)) {
				foreach ($entities as $entity) {
					$em->persist($entity);
				}
			} else {
				$em->persist($entities);
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
			$this->em = null;
		}
	}

	protected function setUpDatabase(): bool
	{
		return true;
	}

}
