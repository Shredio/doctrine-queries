<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Shredio\DoctrineQueries\PhpStan\CriteriaItemType;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineService;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineServiceFactory;
use Shredio\DoctrineQueries\Result\DatabaseColumnValues;
use Shredio\DoctrineQueries\Result\DatabaseIndexedResults;
use Shredio\DoctrineQueries\Result\DatabasePairs;
use Shredio\DoctrineQueries\Result\DatabaseResults;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

abstract readonly class BaseQueriesDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	private const array MethodMap = [
		'findBy' => true,
		'findIndexedBy' => true,
		'findByWithRelations' => true,
		'findPairsBy' => true,
		'findColumnValuesBy' => true,
		'findSingleColumnValueBy' => true,
	];

	private PhpStanDoctrineService $service;

	public function __construct(PhpStanDoctrineServiceFactory $phpStanDoctrineServiceFactory)
	{
		$this->service = $phpStanDoctrineServiceFactory->create();
	}

	abstract protected function isScalar(): bool;

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

		if ($methodName === 'findBy') {
			return $this->fromFindBy($args, $scope, $entity);
		}

		if ($methodName === 'findIndexedBy') {
			return $this->fromFindIndexedBy($args, $scope, $entity);
		}

		if ($methodName === 'findByWithRelations') {
			return $this->fromFindByWithRelations($args, $scope, $entity);
		}

		if ($methodName === 'findPairsBy') {
			return $this->fromFindPairsBy($args, $scope, $entity);
		}

		if ($methodName === 'findColumnValuesBy') {
			return $this->fromFindColumnValuesBy($args, $scope, $entity);
		}

		return $this->fromFindSingleColumnValueBy($args, $scope, $entity);
	}

	/**
	 * @param non-empty-array<Arg> $args
	 * @param class-string $entityClassName
	 */
	private function fromFindBy(
		array $args,
		Scope $scope,
		string $entityClassName,
	): GenericObjectType
	{
		$criteria = $this->getCriteria($scope, $args[1] ?? null);
		$select = $this->getSelect($scope, $entityClassName, $args[3] ?? null);

		$keyTypes = [];
		$valueTypes = [];

		foreach ($select as [$field, $alias]) {
			$keyTypes[] = new ConstantStringType($alias);
			$valueTypes[] = $this->service->determineTypeByFieldCriteria(
				$this->createTypeForField($entityClassName, $field),
				$field,
				$criteria,
			);
		}

		return new GenericObjectType(DatabaseResults::class, [
			new ConstantArrayType($keyTypes, $valueTypes),
		]);
	}

	/**
	 * @param non-empty-array<Arg> $args
	 * @param class-string $entityClassName
	 */
	private function fromFindIndexedBy(
		array $args,
		Scope $scope,
		string $entityClassName,
	): GenericObjectType
	{
		$criteria = $this->getCriteria($scope, $args[2] ?? null);
		$select = $this->getSelect($scope, $entityClassName, $args[4] ?? null);

		$keyTypes = [];
		$valueTypes = [];

		foreach ($select as [$field, $alias]) {
			$keyTypes[] = new ConstantStringType($alias);
			$valueTypes[] = $this->service->determineTypeByFieldCriteria(
				$this->createTypeForField($entityClassName, $field),
				$field,
				$criteria,
			);
		}

		$indexField = $this->service->tryGetSingleStringFromType($scope->getType($args[1]->value));
		if ($indexField === null) {
			$indexType = new MixedType();
		} else {
			$indexType = $this->service->determineTypeByFieldCriteria(
				$this->createTypeForField($entityClassName, $indexField),
				$indexField,
				$criteria,
			);
		}

		return new GenericObjectType(DatabaseIndexedResults::class, [
			$indexType,
			new ConstantArrayType($keyTypes, $valueTypes),
		]);
	}

	/**
	 * @param non-empty-array<Arg> $args
	 * @param class-string $entityClassName
	 */
	private function fromFindByWithRelations(
		array $args,
		Scope $scope,
		string $entityClassName,
	): GenericObjectType
	{
		$criteria = $this->getCriteria($scope, $args[1] ?? null);
		$select = $this->getSelect($scope, $entityClassName, $args[3] ?? null, relations: true);

		$keyTypes = [];
		$valueTypes = [];

		foreach ($select as [$field, $alias]) {
			$keyTypes[] = new ConstantStringType($alias);
			$valueTypes[] = $this->service->determineTypeByFieldCriteria(
				$this->createTypeForField($entityClassName, $field),
				$field,
				$criteria,
			);
		}

		return new GenericObjectType(DatabaseResults::class, [
			new ConstantArrayType($keyTypes, $valueTypes),
		]);
	}

	/**
	 * @param non-empty-array<Arg> $args
	 * @param class-string $entityClassName
	 */
	private function fromFindPairsBy(
		array $args,
		Scope $scope,
		string $entityClassName,
	): ?GenericObjectType
	{
		$keyField = $this->service->tryGetSingleStringFromType($scope->getType($args[1]->value));
		$valueField = $this->service->tryGetSingleStringFromType($scope->getType($args[2]->value));

		if ($keyField === null || $valueField === null) {
			return null;
		}

		$criteria = $this->getCriteria($scope, $args[3] ?? null);


		$keyType = $this->createTypeForField($entityClassName, $keyField, new UnionType([new StringType(), new IntegerType()]));
		$valueType = $this->createTypeForField($entityClassName, $valueField);

		$valueType = $this->service->determineTypeByFieldCriteria($valueType, $valueField, $criteria);

		return new GenericObjectType(DatabasePairs::class, [
			$keyType,
			$valueType,
		]);
	}

	/**
	 * @param non-empty-array<Arg> $args
	 * @param class-string $entityClassName
	 */
	private function fromFindColumnValuesBy(
		array $args,
		Scope $scope,
		string $entityClassName,
	): ?GenericObjectType
	{
		$valueField = $this->service->tryGetSingleStringFromType($scope->getType($args[1]->value));

		if ($valueField === null) {
			return null;
		}

		$criteria = $this->getCriteria($scope, $args[2] ?? null);

		$valueType = $this->createTypeForField($entityClassName, $valueField);
		$valueType = $this->service->determineTypeByFieldCriteria($valueType, $valueField, $criteria);

		return new GenericObjectType(DatabaseColumnValues::class, [
			$valueType,
		]);
	}

	/**
	 * @param non-empty-array<Arg> $args
	 * @param class-string $entityClassName
	 */
	private function fromFindSingleColumnValueBy(
		array $args,
		Scope $scope,
		string $entityClassName,
	): ?Type
	{
		$valueField = $this->service->tryGetSingleStringFromType($scope->getType($args[1]->value));

		if ($valueField === null) {
			return null;
		}

		$criteria = $this->getCriteria($scope, $args[2] ?? null);
		$valueType = $this->createTypeForField($entityClassName, $valueField);

		return TypeCombinator::addNull($this->service->determineTypeByFieldCriteria($valueType, $valueField, $criteria)); // always nullable
	}

	/**
	 * @return list<CriteriaItemType>
	 */
	private function getCriteria(Scope $scope, ?Arg $arg): array
	{
		if ($arg === null) {
			return [];
		}

		return iterator_to_array($this->service->getCriteriaFromType($scope->getType($arg->value)), false);
	}

	/**
	 * @param class-string $entityClassName
	 * @return list<array{ string, string }>
	 */
	private function getSelect(Scope $scope, string $entityClassName, ?Arg $arg, bool $relations = false): array
	{
		if ($arg === null) {
			$select = [];
			foreach ($this->service->getEntityServiceFor($entityClassName)->getSelectionForAllFields($relations) as $fieldName) {
				$select[] = [$fieldName, $fieldName];
			}

			return $select;
		}

		return $this->service->getFieldsFromSelectArrayType($scope->getType($arg->value));
	}

	/**
	 * @param class-string $entityClassName
	 */
	private function createTypeForField(string $entityClassName, string $field, Type $defaultType = new MixedType()): Type
	{
		return $this->service->getEntityServiceFor($entityClassName)->createTypeForField($field, $this->isScalar()) ?? $defaultType;
	}

}
