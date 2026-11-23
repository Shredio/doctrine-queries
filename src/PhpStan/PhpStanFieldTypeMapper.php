<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type as DbalType;
use Doctrine\ORM\EntityManagerInterface;
use PDO;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDriverAwareDescriptor;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use Shredio\DoctrineQueries\Analysis\FieldTypeMapper;
use Shredio\DoctrineQueries\Metadata\FieldType;
use Shredio\DoctrineQueries\Parameter\CriteriaParameterFactory;
use Throwable;

/**
 * Maps the Doctrine types to PHPStan types by the descriptors of phpstan-doctrine, the scalar results by the driver
 * of the connection of the objectManagerLoader.
 *
 * @implements FieldTypeMapper<Type>
 */
final class PhpStanFieldTypeMapper implements FieldTypeMapper
{

	/**
	 * Drivers whose "=" compares strings exactly, MySQL compares case-insensitively with its default collations.
	 * The names are the ones DriverDetector returns.
	 */
	private const array ExactStringComparisonDrivers = ['pdo_sqlite', 'sqlite3', 'pdo_pgsql', 'pgsql'];

	private const array PdoDrivers = ['pdo_mysql', 'pdo_pgsql', 'pdo_sqlite'];

	/** The connection of the objectManagerLoader tells which driver fetches the scalar results. */
	private ?Connection $connection = null;

	private bool $connectionResolved = false;

	private ?AbstractPlatform $databasePlatform = null;

	private bool $databasePlatformResolved = false;

	public function __construct(
		private readonly ObjectMetadataResolver $objectMetadataResolver,
		private readonly DescriptorRegistry $descriptorRegistry,
		// DriverDetector is not covered by the BC promise of phpstan-doctrine, but its driver-aware descriptors use it too,
		// so the detected driver always agrees with the types they return.
		private readonly DriverDetector $driverDetector,
	)
	{
	}

	public function comparesStringsExactly(FieldType $fieldType): bool
	{
		$connection = $this->getConnection();
		if ($connection === null || !in_array($this->driverDetector->detect($connection), self::ExactStringComparisonDrivers, true)) { // @phpstan-ignore phpstanApi.method
			return false;
		}

		if (!DbalType::hasType($fieldType->type)) {
			return false;
		}

		$dbalType = DbalType::getType($fieldType->type);

		return ($dbalType instanceof StringType || $dbalType instanceof TextType) && !$dbalType instanceof GuidType;
	}

	public function getTypeConvertedToDatabaseValue(FieldType $fieldType): ?Type
	{
		try {
			$type = $this->descriptorRegistry->get($fieldType->type)->getWritableToDatabaseType(); // @phpstan-ignore phpstanApi.method
		} catch (DescriptorNotRegisteredException) {
			return null;
		}

		if ($type instanceof MixedType) {
			return null; // converts anything, e.g. json, or the descriptor does not know
		}

		$platform = $this->getDatabasePlatform();
		if ($platform === null || !CriteriaParameterFactory::rejectsUnknownObjects(DbalType::getType($fieldType->type), $platform)) {
			return null;
		}

		return $type;
	}

	/**
	 * @see https://github.com/phpstan/phpstan-doctrine/blob/2.0.x/src/Type/Doctrine/Query/QueryResultTypeWalker.php#L2026
	 */
	public function createType(FieldType $fieldType, bool $databaseValue): Type
	{
		$enumType = $fieldType->enumType;

		try {
			$descriptor = $this->descriptorRegistry->get($fieldType->type); // @phpstan-ignore phpstanApi.method

			if ($databaseValue) {
				$type = $this->resolveDatabaseInternalType($descriptor);
			} else {
				$type = $descriptor->getWritableToPropertyType();
			}

			if (!$databaseValue && $enumType !== null) {
				if ($type->isArray()->no()) {
					$type = new ObjectType($enumType);
				} else {
					$type = TypeCombinator::intersect(new ArrayType(
						$type->getIterableKeyType(),
						new ObjectType($enumType),
					), ...TypeUtils::getAccessoryTypes($type));
				}
			}
			if ($type instanceof NeverType) {
				$type = new MixedType();
			}
		} catch (DescriptorNotRegisteredException) {
			if (!$databaseValue && $enumType !== null) {
				$type = new ObjectType($enumType);
			} else {
				$type = new MixedType();
			}
		}

		if ($fieldType->nullable) {
			$type = TypeCombinator::addNull($type);
		}

		return $type;
	}

	/**
	 * Scalar results are not converted by Doctrine, so their type is the one the driver fetches.
	 *
	 * @see https://github.com/janedbal/php-database-drivers-fetch-test
	 */
	private function resolveDatabaseInternalType(DoctrineTypeDescriptor $descriptor): Type
	{
		$connection = $this->getConnection();
		if ($connection === null) {
			return $descriptor->getDatabaseInternalType();
		}

		$type = $descriptor instanceof DoctrineTypeDriverAwareDescriptor
			? $descriptor->getDatabaseInternalTypeForDriver($connection)
			: $descriptor->getDatabaseInternalType();

		if (!$this->isFetchStringified($connection)) {
			return $type;
		}

		$stringifiedType = TypeCombinator::removeNull($type)->toString();

		return TypeCombinator::containsNull($type) ? TypeCombinator::addNull($stringifiedType) : $stringifiedType;
	}

	/**
	 * PDO returns every value as a string with PDO::ATTR_STRINGIFY_FETCHES, other drivers fetch native types on PHP 8.1+.
	 */
	private function isFetchStringified(Connection $connection): bool
	{
		if (!in_array($this->driverDetector->detect($connection), self::PdoDrivers, true)) { // @phpstan-ignore phpstanApi.method
			return false;
		}

		return (bool) ($this->driverDetector->detectDriverOptions($connection)[PDO::ATTR_STRINGIFY_FETCHES] ?? false); // @phpstan-ignore phpstanApi.method
	}

	/**
	 * The platform the Doctrine types convert the values for. Null without a connection and when the platform cannot
	 * be told, a connection without a configured server version asks the server, which may not be running.
	 */
	private function getDatabasePlatform(): ?AbstractPlatform
	{
		if (!$this->databasePlatformResolved) {
			$this->databasePlatformResolved = true;

			try {
				$this->databasePlatform = $this->getConnection()?->getDatabasePlatform();
			} catch (Throwable) {
				$this->databasePlatform = null;
			}
		}

		return $this->databasePlatform;
	}

	private function getConnection(): ?Connection
	{
		if (!$this->connectionResolved) {
			$this->connectionResolved = true;
			$objectManager = $this->objectMetadataResolver->getObjectManager();
			$this->connection = $objectManager instanceof EntityManagerInterface ? $objectManager->getConnection() : null;
		}

		return $this->connection;
	}

}
