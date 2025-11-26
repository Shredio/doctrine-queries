<?php declare(strict_types = 1);

namespace Tests\Factory;

use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tests\Doctrine\SymbolType;

final readonly class EntityManagerFactory
{

	/**
	 * @param list<Middleware> $middlewares
	 */
	public static function create(array $middlewares = []): EntityManagerInterface
	{
		if (!Type::hasType(SymbolType::Name)) {
			Type::addType(SymbolType::Name, SymbolType::class);
		}

		$config = ORMSetup::createAttributeMetadataConfiguration([
			__DIR__ . '/../Entity',
		], true, cache: new ArrayAdapter());
		$config->setMiddlewares([
			...$config->getMiddlewares(),
			...$middlewares,
		]);

		$connection = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
			'path' => ':memory:',
		], $config);

		return new EntityManager($connection, $config);
	}

}
