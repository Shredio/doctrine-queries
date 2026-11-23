<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Closure;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use LogicException;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;

/**
 * The metadata of the entities as the static analyser loads them, e.g. by the objectManagerLoader of phpstan-doctrine.
 *
 * @implements ClassMetadataFactory<ClassMetadata<object>>
 */
final readonly class AnalysisClassMetadataFactory implements ClassMetadataFactory
{

	/**
	 * @param Closure(class-string): (ClassMetadata<object>|null) $metadataLoader Returns null for a class that is not an entity
	 */
	public function __construct(
		private Closure $metadataLoader,
	)
	{
	}

	public function getAllMetadata(): array
	{
		throw new LogicException('Not implemented');
	}

	/**
	 * @return ClassMetadata<object>
	 * @throws NoClassMetadataException
	 */
	public function getMetadataFor(string $className): ClassMetadata
	{
		return ($this->metadataLoader)($className) ?? throw new NoClassMetadataException($className);
	}

	public function hasMetadataFor(string $className): bool
	{
		throw new LogicException('Not implemented');
	}

	/**
	 * @param ClassMetadata<object> $class
	 */
	public function setMetadataFor(string $className, PersistenceClassMetadata $class): void
	{
		throw new LogicException('Not implemented');
	}

	public function isTransient(string $className): bool
	{
		throw new LogicException('Not implemented');
	}

}
