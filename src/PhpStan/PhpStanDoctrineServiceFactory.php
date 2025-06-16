<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @api
 */
final readonly class PhpStanDoctrineServiceFactory
{

	public function __construct(
		private ObjectMetadataResolver $objectMetadataResolver,
		private DescriptorRegistry $descriptorRegistry,
	)
	{
	}

	public function create(): PhpStanDoctrineService
	{
		return new PhpStanDoctrineService(
			$this->objectMetadataResolver,
			$this->descriptorRegistry
		);
	}

}
