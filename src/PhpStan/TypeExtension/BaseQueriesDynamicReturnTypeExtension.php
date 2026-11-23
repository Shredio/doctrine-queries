<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Shredio\DoctrineQueries\Analysis\DoctrineQueriesAnalyzer;
use Shredio\DoctrineQueries\PhpStan\PhpStanAnalyzerFactory;

abstract readonly class BaseQueriesDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	/** @var DoctrineQueriesAnalyzer<Type> */
	private DoctrineQueriesAnalyzer $analyzer;

	public function __construct(PhpStanAnalyzerFactory $analyzerFactory)
	{
		$this->analyzer = $analyzerFactory->create();
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return DoctrineQueriesAnalyzer::hasReturnType($this->getClass(), $methodReflection->getName());
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
		return $this->analyzer->getReturnType(
			$this->getClass(),
			$methodReflection->getName(),
			PhpStanAnalyzerFactory::createArguments($methodCall->getArgs(), $scope),
		);
	}

}
