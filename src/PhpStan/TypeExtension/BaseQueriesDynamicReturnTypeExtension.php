<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineService;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineServiceFactory;
use Shredio\DoctrineQueries\Result\DatabaseColumnValues;
use Shredio\DoctrineQueries\Result\DatabaseIndexedResults;
use Shredio\DoctrineQueries\Result\DatabasePairs;
use Shredio\DoctrineQueries\Result\DatabaseResults;
use Shredio\DoctrineQueries\Select\QueryType;

abstract readonly class BaseQueriesDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	private const array MethodMap = [
		'findBy' => true,
		'findOneBy' => true,
		'findIndexedBy' => true,
		'findPairsBy' => true,
		'findColumnValuesBy' => true,
		'findSingleColumnValueBy' => true,
	];

	private PhpStanDoctrineService $service;

	public function __construct(PhpStanDoctrineServiceFactory $phpStanDoctrineServiceFactory)
	{
		$this->service = $phpStanDoctrineServiceFactory->create();
	}

	abstract protected function getQueryType(): QueryType;

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return isset(self::MethodMap[$methodReflection->getName()]);
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
		$args = $methodCall->getArgs();

		if (!isset($args[0])) {
			return null;
		}

		$entity = $this->service->tryGetSingleStringFromType($scope->getType($args[0]->value));

		if ($entity === null || !class_exists($entity)) {
			return null;
		}

		$methodName = $methodReflection->getName();

		$context = new DynamicReturnTypeContext(
			$args,
			$scope,
			$entity,
			$this->getQueryType(),
			$this->service,
		);

		if ($methodName === 'findBy') {
			return $this->fromFindBy($context);
		}

		if ($methodName === 'findIndexedBy') {
			return $this->fromFindIndexedBy($context);
		}

		if ($methodName === 'findPairsBy') {
			return $this->fromFindPairsBy($context);
		}

		if ($methodName === 'findColumnValuesBy') {
			return $this->fromFindColumnValuesBy($context);
		}

		if ($methodName === 'findOneBy') {
			return $this->fromFindOneBy($context);
		}

		return $this->fromFindSingleColumnValueBy($context);
	}

	private function fromFindBy(DynamicReturnTypeContext $context): GenericObjectType
	{
		$criteria = $context->getCriteria(1);
		$selectType = $context->getSelectType(3, $criteria, 4);

		return new GenericObjectType(DatabaseResults::class, [
			$selectType,
		]);
	}

	private function fromFindOneBy(DynamicReturnTypeContext $context): Type
	{
		$criteria = $context->getCriteria(1);
		$selectType = $context->getSelectType(3, $criteria, 4);

		return TypeCombinator::addNull($selectType);
	}

	private function fromFindIndexedBy(DynamicReturnTypeContext $context): GenericObjectType
	{
		$criteria = $context->getCriteria(2);
		$selectType = $context->getSelectType(4, $criteria, 5);
		$indexType = $context->tryToCreateTypeFromConstantType(1, $criteria);

		return new GenericObjectType(DatabaseIndexedResults::class, [
			$indexType,
			$selectType,
		]);
	}

	private function fromFindPairsBy(DynamicReturnTypeContext $context): GenericObjectType
	{
		$criteria = $context->getCriteria(3);
		$keyType = $context->tryToCreateTypeFromConstantType(1, $criteria);
		$valueType = $context->tryToCreateTypeFromConstantType(2, $criteria);

		if ($keyType instanceof MixedType) {
			$keyType = new UnionType([new StringType(), new IntegerType()]);
		}

		return new GenericObjectType(DatabasePairs::class, [
			$keyType,
			$valueType,
		]);
	}

	private function fromFindColumnValuesBy(DynamicReturnTypeContext $context): GenericObjectType
	{
		$criteria = $context->getCriteria(2);
		$valueType = $context->tryToCreateTypeFromConstantType(1, $criteria);

		return new GenericObjectType(DatabaseColumnValues::class, [
			$valueType,
		]);
	}

	private function fromFindSingleColumnValueBy(DynamicReturnTypeContext $context): Type
	{
		$criteria = $context->getCriteria(2);
		$valueType = $context->tryToCreateTypeFromConstantType(1, $criteria);

		return TypeCombinator::addNull($valueType); // always nullable
	}

}
