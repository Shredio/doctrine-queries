<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Shredio\DoctrineQueries\Analysis\DoctrineQueriesAnalyzer;
use Shredio\DoctrineQueries\Criteria\Criteria;
use Shredio\DoctrineQueries\PhpStan\PhpStanAnalyzerFactory;

/**
 * Returns Criteria::anyOf() with the type of each alternative as it was passed, AnyOf<array{TFirst, TSecond}>.
 *
 * The declared AnyOf<list<TFirst|TSecond>> is not enough to validate the alternatives: PHPStan merges the array shapes
 * of a union (['id' => 1] and [] become array{id?: int}) and generalizes a union of many shapes to a common array.
 */
final readonly class CriteriaDynamicReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{

	/** @var DoctrineQueriesAnalyzer<Type> */
	private DoctrineQueriesAnalyzer $analyzer;

	public function __construct(PhpStanAnalyzerFactory $analyzerFactory)
	{
		$this->analyzer = $analyzerFactory->create();
	}

	public function getClass(): string
	{
		return Criteria::class;
	}

	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'anyOf';
	}

	public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): ?Type
	{
		if ($methodCall->isFirstClassCallable()) {
			return null;
		}

		return $this->analyzer->getAnyOfReturnType(PhpStanAnalyzerFactory::createArguments($methodCall->getArgs(), $scope));
	}

}
