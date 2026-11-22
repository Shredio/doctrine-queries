<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use PHPStan\Doctrine\Driver\DriverDetector;
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
		private DriverDetector $driverDetector = new DriverDetector(), // @phpstan-ignore phpstanApi.constructor (see PhpStanDoctrineService)
	)
	{
	}

	public function create(): PhpStanDoctrineService
	{
		return new PhpStanDoctrineService(
			$this->objectMetadataResolver,
			$this->descriptorRegistry,
			$this->driverDetector,
		);
	}

}
