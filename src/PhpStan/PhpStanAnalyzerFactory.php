<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Type;
use Shredio\DoctrineQueries\Analysis\AnalysisClassMetadataFactory;
use Shredio\DoctrineQueries\Analysis\CallArgument;
use Shredio\DoctrineQueries\Analysis\CallArguments;
use Shredio\DoctrineQueries\Analysis\DoctrineQueriesAnalyzer;

/**
 * @api
 */
final readonly class PhpStanAnalyzerFactory
{

	public function __construct(
		private ObjectMetadataResolver $objectMetadataResolver,
		private DescriptorRegistry $descriptorRegistry,
		private DriverDetector $driverDetector = new DriverDetector(), // @phpstan-ignore phpstanApi.constructor (see PhpStanFieldTypeMapper)
	)
	{
	}

	/**
	 * @param array<Arg> $args
	 * @return CallArguments<Type>
	 */
	public static function createArguments(array $args, Scope $scope): CallArguments
	{
		$arguments = [];
		foreach ($args as $arg) {
			$arguments[] = new CallArgument($scope->getType($arg->value), $arg->name?->toString(), $arg->unpack);
		}

		return new CallArguments($arguments);
	}

	/**
	 * @return DoctrineQueriesAnalyzer<Type>
	 */
	public function create(): DoctrineQueriesAnalyzer
	{
		return new DoctrineQueriesAnalyzer(
			new PhpStanTypeSystem(),
			new PhpStanFieldTypeMapper($this->objectMetadataResolver, $this->descriptorRegistry, $this->driverDetector),
			new AnalysisClassMetadataFactory($this->objectMetadataResolver->getClassMetadata(...)),
		);
	}

}
