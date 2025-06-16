<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use LogicException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

final class PhpStanDoctrineEntityServiceFactory
{

	/** @var array<class-string, PhpStanDoctrineEntityService<object>> */
	private array $services = [];

	public function __construct(
		private readonly ObjectMetadataResolver $objectMetadataResolver,
		private readonly DescriptorRegistry $descriptorRegistry,
	)
	{
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entityClass
	 * @return PhpStanDoctrineEntityService<T>
	 */
	public function create(string $entityClass): PhpStanDoctrineEntityService
	{
		if (isset($this->services[$entityClass])) {
			/** @var PhpStanDoctrineEntityService<T> */
			return $this->services[$entityClass];
		}

		$metadata = $this->objectMetadataResolver->getClassMetadata($entityClass);

		if (!$metadata) {
			throw new LogicException(sprintf('No metadata found for entity class "%s".', $entityClass));
		}

		return $this->services[$entityClass] = new PhpStanDoctrineEntityService(
			$this->descriptorRegistry,
			$this,
			$metadata,
		);
	}

}
