<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineService;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineServiceFactory;
use Shredio\DoctrineQueries\Result\DatabaseExistenceResults;
use Shredio\DoctrineQueries\Select\QueryType;

final readonly class DoctrineQueriesDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	private PhpStanDoctrineService $service;

	public function __construct(PhpStanDoctrineServiceFactory $phpStanDoctrineServiceFactory)
	{
		$this->service = $phpStanDoctrineServiceFactory->create();
	}

	public function getClass(): string
	{
		return DoctrineQueries::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'existsManyBy';
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

		if (!isset($args[1])) {
			return null;
		}

		$criteriaType = $scope->getType($args[1]->value);
		if (!$criteriaType->isArray()->yes()) {
			return new GenericObjectType(DatabaseExistenceResults::class, [new NeverType()]);
		}

		$queryMetadata = $this->service->getQueryMetadataFor($entity, QueryType::Scalar);
		$arrayBuilder = ConstantArrayTypeBuilder::createEmpty();
		foreach ($criteriaType->getArrays() as $arrayType) {
			$constantArrays = $arrayType->getItemType()->getConstantArrays();
			if ($constantArrays === []) {
				return new GenericObjectType(DatabaseExistenceResults::class, [new NeverType()]);
			}

			foreach ($constantArrays as $constantArray) {
				$criteriaTypes = $this->service->getCriteriaFromType($constantArray);
				foreach ($criteriaTypes as $item) {
					try {
						$fieldMetadata = $queryMetadata->getFieldMetadata($item->field);
					} catch (FieldNotExistsException|InvalidAssociationPathException) {
						return new GenericObjectType(DatabaseExistenceResults::class, [new NeverType()]);
					}

					$fieldType = $this->service->createTypeForFieldMapping(
						$fieldMetadata->getFieldType(),
						true,
					);

					$arrayBuilder->setOffsetValueType(new ConstantStringType($item->field->name), $fieldType);
				}
			}
		}

		return new GenericObjectType(DatabaseExistenceResults::class, [
			$arrayBuilder->getArray(),
		]);
	}

}
