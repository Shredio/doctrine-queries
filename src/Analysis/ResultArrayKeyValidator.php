<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * Reports asArray() of findPairsBy(), findIndexedBy() and the grouped aggregations (countGroupedBy(), sumGroupedBy(),
 * minGroupedBy(), maxGroupedBy()) whose key can be NULL or a value PHP arrays do not accept as a key (e.g. an enum or a date hydrated by ArrayQueries), asArray() throws on such a row.
 *
 * @template TType of object
 * @internal
 */
final readonly class ResultArrayKeyValidator
{

	/**
	 * The parameter with the key field of each method.
	 */
	private const array KeyParameters = [
		'findPairsBy' => 'key',
		'findIndexedBy' => 'indexField',
		'countGroupedBy' => 'groupBy',
		'sumGroupedBy' => 'groupBy',
		'minGroupedBy' => 'groupBy',
		'maxGroupedBy' => 'groupBy',
	];

	/**
	 * @param TypeSystem<TType> $types
	 * @param QueryReturnTypeResolver<TType> $returnTypeResolver
	 */
	public function __construct(
		private TypeSystem $types,
		private QueryReturnTypeResolver $returnTypeResolver,
	)
	{
	}

	public static function isFinder(string $finderClass, string $finderMethod): bool
	{
		return isset(self::KeyParameters[$finderMethod]) && QueryMethods::getQueryType($finderClass, $finderMethod) !== null;
	}

	/**
	 * @param CallArguments<TType> $finderArguments The arguments of the finder whose result asArray() is called on
	 * @return list<AnalysisError>
	 */
	public function validate(string $finderClass, string $finderMethod, CallArguments $finderArguments): array
	{
		$keyParameter = self::KeyParameters[$finderMethod] ?? null;
		$queryType = QueryMethods::getQueryType($finderClass, $finderMethod);
		$parameters = QueryMethods::getParameters($finderClass, $finderMethod);
		if ($keyParameter === null || $queryType === null || $parameters === null) {
			return [];
		}

		$entityClassName = $this->returnTypeResolver->getEntityClassName($finderArguments);
		if ($entityClassName === null) {
			return []; // reported by the validation of the call
		}

		try {
			$context = $this->returnTypeResolver->createContext($finderArguments, $parameters, $entityClassName, $queryType);
		} catch (NoClassMetadataException) {
			return []; // reported by the validation of the call
		}

		$errors = [];
		// each of the fields when the key is one of several, none when it is not known (reported by the validation of the call)
		foreach ($context->getFieldArgumentNames($keyParameter) as $keyField) {
			array_push($errors, ...$this->validateKeyField($context, $keyField, $finderClass, $finderMethod, $queryType));
		}

		return $errors;
	}

	/**
	 * @param QueryCallContext<TType> $context
	 * @return list<AnalysisError>
	 */
	private function validateKeyField(QueryCallContext $context, string $keyField, string $finderClass, string $finderMethod, QueryType $queryType): array
	{
		$keyType = $context->getFieldType($keyField, $context->getCriteriaVariants());
		if ($keyType === null || $this->types->isMixed($keyType)) {
			return []; // an unknown field is reported by the validation of the call, an unknown type cannot be judged
		}

		$keyTypeWithoutNull = $this->types->removeNull($keyType);
		if (!$this->types->isArrayKey($keyTypeWithoutNull)) {
			return [
				new AnalysisError(
					sprintf(
						'Call to method %s::%s() - the key `%s` is returned as %s, but asArray() accepts only integers and strings as keys.%s Iterate over yield() to keep the keys as they are.',
						$finderClass,
						$finderMethod,
						$keyField,
						$this->types->describe($keyTypeWithoutNull),
						$queryType === QueryType::Array ? sprintf(' Use %s to get the database values.', ScalarQueries::class) : '',
					),
					'invalidArrayKey',
				),
			];
		}

		if ($this->types->containsNull($keyType)) {
			return [
				new AnalysisError(
					sprintf(
						'Call to method %s::%s() - the key `%s` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'%s !=\' => null], or iterate over yield().',
						$finderClass,
						$finderMethod,
						$keyField,
						$keyField,
					),
					'nullableArrayKey',
				),
			];
		}

		return [];
	}

}
