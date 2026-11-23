<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

/**
 * The static analysis of the queries shared by the extensions of static analysers (PHPStan, Mago). An extension
 * implements TypeSystem and FieldTypeMapper with the types of its analyser, passes the types of the arguments
 * of the calls and reports the returned errors and types.
 *
 * @template TType of object
 */
final readonly class DoctrineQueriesAnalyzer
{

	/** @var QueryReturnTypeResolver<TType> */
	private QueryReturnTypeResolver $returnTypeResolver;

	/** @var QueryCallValidator<TType> */
	private QueryCallValidator $callValidator;

	/** @var ResultArrayKeyValidator<TType> */
	private ResultArrayKeyValidator $resultArrayKeyValidator;

	/**
	 * @param TypeSystem<TType> $types
	 * @param FieldTypeMapper<TType> $fieldTypeMapper
	 */
	public function __construct(
		TypeSystem $types,
		FieldTypeMapper $fieldTypeMapper,
		AnalysisClassMetadataFactory $classMetadataFactory,
	)
	{
		$reader = new QueryArgumentReader($types);
		$this->returnTypeResolver = new QueryReturnTypeResolver(
			$types,
			$classMetadataFactory,
			$reader,
			new FieldTypeResolver($types, $fieldTypeMapper),
		);
		$this->callValidator = new QueryCallValidator(
			$types,
			$classMetadataFactory,
			$reader,
			new CriteriaValueValidator($types, $fieldTypeMapper),
		);
		$this->resultArrayKeyValidator = new ResultArrayKeyValidator($types, $this->returnTypeResolver);
	}

	/**
	 * Whether the return type of the method is inferred, a cheap check before the types of the arguments are resolved.
	 */
	public static function hasReturnType(string $className, string $methodName): bool
	{
		return QueryMethods::hasReturnType($className, $methodName);
	}

	/**
	 * Whether the calls of the method are validated, a cheap check before the types of the arguments are resolved.
	 */
	public static function validatesCall(string $className, string $methodName): bool
	{
		return QueryMethods::getParameters($className, $methodName) !== null;
	}

	/**
	 * Whether asArray() called on the result of the method is validated.
	 */
	public static function validatesResultArrayKey(string $finderClass, string $finderMethod): bool
	{
		return ResultArrayKeyValidator::isFinder($finderClass, $finderMethod);
	}

	/**
	 * @param CallArguments<TType> $arguments
	 * @return TType|null Null when the declared return type applies
	 */
	public function getReturnType(string $calledOnClass, string $methodName, CallArguments $arguments): ?object
	{
		return $this->returnTypeResolver->resolve($calledOnClass, $methodName, $arguments);
	}

	/**
	 * The return type of Criteria::anyOf().
	 *
	 * @param CallArguments<TType> $arguments
	 * @return TType|null Null when the declared return type applies
	 */
	public function getAnyOfReturnType(CallArguments $arguments): ?object
	{
		return $this->returnTypeResolver->resolveAnyOf($arguments);
	}

	/**
	 * @param CallArguments<TType> $arguments
	 * @return list<AnalysisError>
	 */
	public function validateCall(string $calledOnClass, string $methodName, CallArguments $arguments): array
	{
		return $this->callValidator->validate($calledOnClass, $methodName, $arguments);
	}

	/**
	 * Validates asArray() called on the result of findPairsBy(), findIndexedBy() or a grouped aggregation, e.g. countGroupedBy().
	 *
	 * @param CallArguments<TType> $finderArguments
	 * @return list<AnalysisError>
	 */
	public function validateResultArrayKey(string $finderClass, string $finderMethod, CallArguments $finderArguments): array
	{
		return $this->resultArrayKeyValidator->validate($finderClass, $finderMethod, $finderArguments);
	}

}
