<?php declare(strict_types = 1);

namespace Tests\Factory;

use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tests\Doctrine\SymbolType;

final readonly class EntityManagerFactory
{

	/**
	 * Selects the database for the tests and for the PHPStan analysis of the test data, e.g.
	 * DATABASE_URL="pdo-mysql://root:root@127.0.0.1:3307/doctrine_queries". In-memory SQLite is used without it.
	 */
	private const string DatabaseUrlVariable = 'DATABASE_URL';

	private const array InMemoryConnectionParameters = [
		'driver' => 'pdo_sqlite',
		'path' => ':memory:',
	];

	/**
	 * Uses the database from DATABASE_URL.
	 *
	 * @param list<Middleware> $middlewares
	 */
	public static function create(array $middlewares = []): EntityManagerInterface
	{
		return self::createForConnection(self::getConnectionParameters(), $middlewares);
	}

	/**
	 * Ignores DATABASE_URL, for the PHPStan tests whose expected types are the ones of SQLite.
	 *
	 * @param array<int, mixed> $driverOptions e.g. [PDO::ATTR_STRINGIFY_FETCHES => true]
	 */
	public static function createInMemory(array $driverOptions = []): EntityManagerInterface
	{
		return self::createForConnection([...self::InMemoryConnectionParameters, 'driverOptions' => $driverOptions]);
	}

	/**
	 * @param array<string, mixed> $connectionParameters
	 * @param list<Middleware> $middlewares
	 */
	private static function createForConnection(array $connectionParameters, array $middlewares = []): EntityManagerInterface
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

		if (PHP_VERSION_ID >= 80400) {
			// Doctrine ORM 3.6+ allows symfony/var-exporter 8, which no longer ships the LazyGhost proxies.
			$config->enableNativeLazyObjects(true);
		}

		$connection = DriverManager::getConnection($connectionParameters, $config); // @phpstan-ignore argument.type

		return new EntityManager($connection, $config);
	}

	public static function isInMemoryDatabase(): bool
	{
		return self::getDatabaseUrl() === null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getConnectionParameters(): array
	{
		$databaseUrl = self::getDatabaseUrl();
		if ($databaseUrl === null) {
			return self::InMemoryConnectionParameters;
		}

		$parameters = (new DsnParser())->parse($databaseUrl);

		// e.g. ?driverOptions[17]=1 for PDO::ATTR_STRINGIFY_FETCHES, PDO does not accept the numeric strings of a URL
		if (isset($parameters['driverOptions']) && is_array($parameters['driverOptions'])) {
			$parameters['driverOptions'] = array_map(
				static fn (mixed $value): mixed => is_string($value) && is_numeric($value) ? (int) $value : $value,
				$parameters['driverOptions'],
			);
		}

		return $parameters;
	}

	private static function getDatabaseUrl(): ?string
	{
		$databaseUrl = getenv(self::DatabaseUrlVariable);

		return is_string($databaseUrl) && $databaseUrl !== '' ? $databaseUrl : null;
	}

}
