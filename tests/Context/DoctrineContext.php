<?php declare(strict_types = 1);

namespace Tests\Context;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tests\Doctrine\Symbol;
use Tests\Doctrine\SymbolType;
use Tests\Doctrine\TestManagerRegistry;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\Factory\EntityManagerFactory;

trait DoctrineContext
{

	private ?EntityManagerInterface $em = null;

	private function getEntityManager(): EntityManager
	{
		if ($this->em !== null) {
			return $this->em;
		}

		$this->em = $em = EntityManagerFactory::create();

		if ($this->setUpDatabase()) {
			$schemaTool = new SchemaTool($em);
			$schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
		}

		return $em;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @return ClassMetadata<T>
	 */
	private function getMetadata(string $className): ClassMetadata
	{
		return $this->getEntityManager()->getClassMetadata($className);
	}

	private function createManagerRegistry(): TestManagerRegistry
	{
		return new TestManagerRegistry($this->getEntityManager());
	}

	private function persistFixtures(): void
	{
		$em = $this->getEntityManager();
		$em->persist($author = new Author(1, 'John Doe'));
		$em->persist($author2 = new Author(2, 'Jane Smith'));
		$em->persist(new Article(1, 'Sample Article', 'This is a sample article.', $author, new Symbol('sym')));
		$em->persist(new Article(2, 'Another Article', 'This is another article.', $author2));
		$em->persist(new Article(3, 'Third Article', 'This is the third article.', $author));
		$em->flush();
	}

	protected function setUpDatabase(): bool
	{
		return true;
	}

}
