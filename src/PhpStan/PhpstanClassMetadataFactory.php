<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use LogicException;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;

/**
 * @implements ClassMetadataFactory<ClassMetadata<object>>
 */
final readonly class PhpstanClassMetadataFactory implements ClassMetadataFactory
{

	public function __construct(
		private ObjectMetadataResolver $objectMetadataResolver,
	)
	{
	}

	public function getAllMetadata(): array
	{
		throw new LogicException('Not implemented');
	}

	/**
	 * @return ClassMetadata<object>
	 */
	public function getMetadataFor(string $className): ClassMetadata
	{
		return $this->objectMetadataResolver->getClassMetadata($className) ?? throw new NoClassMetadataException($className);
	}

	public function hasMetadataFor(string $className): bool
	{
		throw new LogicException('Not implemented');
	}

	/**
	 * @param ClassMetadata<object> $class
	 */
	public function setMetadataFor(string $className, \Doctrine\Persistence\Mapping\ClassMetadata $class): void
	{
		throw new LogicException('Not implemented');
	}

	public function isTransient(string $className): bool
	{
		throw new LogicException('Not implemented');
	}

}
