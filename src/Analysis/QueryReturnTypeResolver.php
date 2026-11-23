<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Aggregation\SumKind;
use Shredio\DoctrineQueries\Criteria\AnyOf;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\Exception\UnsupportedFieldTypeException;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Result\DatabaseColumnValues;
use Shredio\DoctrineQueries\Result\DatabaseExistenceResults;
use Shredio\DoctrineQueries\Result\DatabaseIndexedResults;
use Shredio\DoctrineQueries\Result\DatabasePairs;
use Shredio\DoctrineQueries\Result\DatabaseResults;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * Infers the return types of the queries from the entity metadata and the arguments.
 *
 * @template TType of object
 * @internal
 */
final readonly class QueryReturnTypeResolver
{

	/**
	 * @param TypeSystem<TType> $types
	 * @param QueryArgumentReader<TType> $reader
	 * @param FieldTypeResolver<TType> $fieldTypeResolver
	 */
	public function __construct(
		private TypeSystem $types,
		private AnalysisClassMetadataFactory $classMetadataFactory,
		private QueryArgumentReader $reader,
		private FieldTypeResolver $fieldTypeResolver,
	)
	{
	}

	/**
	 * @param CallArguments<TType> $arguments
	 * @return TType|null Null when the declared return type applies
	 */
	public function resolve(string $calledOnClass, string $methodName, CallArguments $arguments): ?object
	{
		if (!QueryMethods::hasReturnType($calledOnClass, $methodName)) {
			return null;
		}

		foreach ($arguments->arguments as $argument) {
			if ($argument->unpacked) {
				return null; // the parameters the unpacked values are passed to are not known
			}
		}

		$entityClassName = $this->getEntityClassName($arguments);
		if ($entityClassName === null) {
			return null;
		}

		if ($calledOnClass === DoctrineQueries::class && $methodName === 'existsManyBy') {
			return $this->resolveExistsManyBy($entityClassName, $arguments);
		}

		$queryType = QueryMethods::getQueryType($calledOnClass, $methodName);
		$parameters = QueryMethods::getParameters($calledOnClass, $methodName);
		if ($queryType === null || $parameters === null) {
			return null;
		}

		try {
			$context = $this->createContext($arguments, $parameters, $entityClassName, $queryType);
		} catch (NoClassMetadataException) {
			return null; // not an entity, reported by the validation
		}

		$criteriaVariants = $context->getCriteriaVariants();
		$rowType = in_array($methodName, ['findBy', 'findOneBy', 'findIndexedBy'], true) ? $context->getSelectType($criteriaVariants) : null;

		return match ($methodName) {
			'findBy' => $rowType === null ? null : $this->types->genericObject(DatabaseResults::class, [$rowType]),
			'findOneBy' => $rowType === null ? null : $this->types->addNull($rowType),
			'findIndexedBy' => $rowType === null ? null : $this->types->genericObject(DatabaseIndexedResults::class, [
				$this->toKeyType($context->getFieldArgumentType('indexField', $criteriaVariants) ?? $this->types->mixed()),
				$rowType,
			]),
			'findPairsBy' => $this->types->genericObject(DatabasePairs::class, [
				$this->toPairsKeyType($context->getFieldArgumentType('key', $criteriaVariants)),
				$context->getFieldArgumentType('value', $criteriaVariants) ?? $this->types->mixed(),
			]),
			'findColumnValuesBy' => $this->types->genericObject(DatabaseColumnValues::class, [
				$context->getFieldArgumentType('field', $criteriaVariants) ?? $this->types->mixed(),
			]),
			'findSingleColumnValueBy' => $this->types->addNull( // always nullable
				$context->getFieldArgumentType('field', $criteriaVariants) ?? $this->types->mixed(),
			),
			'countGroupedBy' => $this->createGroupedResults($context, $criteriaVariants, $this->getGroupedCountType($context, $criteriaVariants)),
			'sumBy' => $this->types->addNull($this->getSumType($context)), // null for no entity
			'minBy', 'maxBy' => $this->types->addNull($this->getAggregatedFieldType($context, $criteriaVariants)),
			'sumGroupedBy' => $this->createGroupedResults($context, $criteriaVariants, $this->getGroupedSumType($context, $criteriaVariants)),
			'minGroupedBy', 'maxGroupedBy' => $this->createGroupedResults($context, $criteriaVariants, $this->getAggregatedFieldType($context, $criteriaVariants)),
			default => null,
		};
	}

	/**
	 * The type of Criteria::anyOf() with the type of each alternative as it was passed, AnyOf<array{TFirst, TSecond}>.
	 *
	 * The declared AnyOf<list<TFirst|TSecond>> is not enough to validate the alternatives: an analyser can merge the array
	 * shapes of a union (['id' => 1] and [] become array{id?: int}) and generalize a union of many shapes to a common array.
	 *
	 * @param CallArguments<TType> $arguments
	 * @return TType|null
	 */
	public function resolveAnyOf(CallArguments $arguments): ?object
	{
		$alternativeTypes = [];
		foreach ($arguments->arguments as $argument) {
			if (!$argument->unpacked) {
				$alternativeTypes[] = $argument->type;

				continue;
			}

			$unpackedAlternatives = $this->types->getConstantArrays($argument->type);
			if (count($unpackedAlternatives) !== 1 || !$this->types->isConstantArray($argument->type)) {
				return null; // the number of the alternatives is not known
			}

			foreach ($unpackedAlternatives[0]->items as $item) {
				if ($item->optional) {
					return null; // the number of the alternatives is not known
				}

				$alternativeTypes[] = $item->valueType;
			}
		}

		return $this->types->genericObject(AnyOf::class, [$this->types->constantArray($alternativeTypes)]);
	}

	/**
	 * @param CallArguments<TType> $arguments
	 * @param array<int, string> $parameters
	 * @param class-string $entityClassName
	 * @return QueryCallContext<TType>
	 * @throws NoClassMetadataException
	 */
	public function createContext(CallArguments $arguments, array $parameters, string $entityClassName, QueryType $queryType): QueryCallContext
	{
		return new QueryCallContext(
			$arguments,
			$parameters,
			$this->getQueryMetadata($entityClassName, $queryType),
			$queryType,
			$this->types,
			$this->reader,
			$this->fieldTypeResolver,
		);
	}

	/**
	 * The entity passed as a constant string of an existing class, null otherwise, also for one of several entities.
	 *
	 * @param CallArguments<TType> $arguments
	 * @return class-string|null
	 */
	public function getEntityClassName(CallArguments $arguments): ?string
	{
		$type = $arguments->getType(QueryMethods::EntityPosition, QueryMethods::EntityName);
		$entityClassName = $type === null ? null : $this->reader->getOnlyString($type);

		return $entityClassName !== null && class_exists($entityClassName) ? $entityClassName : null;
	}

	/**
	 * @param class-string $entityClassName
	 * @param CallArguments<TType> $arguments
	 * @return TType|null
	 */
	private function resolveExistsManyBy(string $entityClassName, CallArguments $arguments): ?object
	{
		$valuesType = $arguments->getType(1, 'values');
		if ($valuesType === null) {
			return null;
		}

		if (!$this->types->isArray($valuesType)) {
			return $this->createExistenceResults(null);
		}

		try {
			$queryMetadata = $this->getQueryMetadata($entityClassName, QueryType::Scalar);
		} catch (NoClassMetadataException) {
			return null; // not an entity, reported by the validation
		}

		$rows = $this->types->getConstantArrays($this->types->getIterableValueType($valuesType));
		if ($rows === []) {
			return $this->createExistenceResults(null);
		}

		$valueTypes = [];
		foreach ($rows as $row) {
			foreach ($row->items as $item) {
				if (!is_string($item->key)) {
					continue;
				}

				[$field] = CriteriaParser::parseSingleField($item->key);

				try {
					$fieldType = $queryMetadata->getFieldMetadata($field)->getFieldType();
				} catch (FieldNotExistsException|InvalidAssociationPathException|UnsupportedFieldTypeException) {
					return $this->createExistenceResults(null);
				}

				$valueTypes[$field->name] = $this->fieldTypeResolver->createType($fieldType, true);
			}
		}

		return $this->createExistenceResults($valueTypes);
	}

	/**
	 * @param array<string, TType>|null $valueTypes The types of the compared fields, null when they are not known
	 * @return TType
	 */
	private function createExistenceResults(?array $valueTypes): object
	{
		return $this->types->genericObject(DatabaseExistenceResults::class, [
			$valueTypes === null ? $this->types->never() : $this->types->constantArray($valueTypes),
		]);
	}

	/**
	 * The pairs of the database values of the group field and their aggregations.
	 *
	 * @param QueryCallContext<TType> $context
	 * @param list<list<CriteriaItem<TType>>> $criteriaVariants
	 * @param TType $valueType
	 * @return TType
	 */
	private function createGroupedResults(QueryCallContext $context, array $criteriaVariants, object $valueType): object
	{
		return $this->types->genericObject(DatabasePairs::class, [
			$this->toPairsKeyType($context->getFieldArgumentType('groupBy', $criteriaVariants)),
			$valueType,
		]);
	}

	/**
	 * The sum by the kind of the summed field, see SumKind. Without null, which the sum of no value is.
	 * The sum of an integer column mapped as unsigned is not negative.
	 *
	 * @param QueryCallContext<TType> $context
	 * @return TType
	 */
	private function getSumType(QueryCallContext $context): object
	{
		$sumTypes = [];

		foreach ($context->getFieldArgumentFieldTypes('field') ?? [] as $fieldType) {
			$sumType = match (SumKind::fromDoctrineType($fieldType->type)) {
				SumKind::Integer => $this->types->intRange($fieldType->unsigned ? 0 : null, null),
				SumKind::Decimal => $this->types->numericString(),
				SumKind::Float => $this->types->float(),
				SumKind::FieldValue => $this->types->removeNull($this->fieldTypeResolver->createType($fieldType, false)),
				null => null,
			};

			if ($sumType === null) {
				$sumTypes = [];

				break; // reported by the validation
			}

			$sumTypes[] = $sumType;
		}

		// the declared type when the field is not known
		return $this->types->union(...($sumTypes !== [] ? $sumTypes : [$this->types->intRange(null, null), $this->types->float(), $this->types->numericString()]));
	}

	/**
	 * A group has an entity, so its sum is null only when the summed field can be NULL.
	 *
	 * @param QueryCallContext<TType> $context
	 * @param list<list<CriteriaItem<TType>>> $criteriaVariants
	 * @return TType
	 */
	private function getGroupedSumType(QueryCallContext $context, array $criteriaVariants): object
	{
		$sumType = $this->getSumType($context);
		// The type of the PHP value, the type the driver returns is known for fewer custom types and null is added to both.
		$fieldType = $context->getFieldArgumentType('field', $criteriaVariants, QueryType::Array);

		return $fieldType === null || $this->types->canBeNull($fieldType) ? $this->types->addNull($sumType) : $sumType;
	}

	/**
	 * MIN() and MAX() return a value of the field, converted by its type as a field selected by ArrayQueries is.
	 * A nullable field is null when all the aggregated values are NULL.
	 *
	 * @param QueryCallContext<TType> $context
	 * @param list<list<CriteriaItem<TType>>> $criteriaVariants
	 * @return TType
	 */
	private function getAggregatedFieldType(QueryCallContext $context, array $criteriaVariants): object
	{
		return $context->getFieldArgumentType('field', $criteriaVariants, QueryType::Array) ?? $this->types->mixed();
	}

	/**
	 * A group exists only for a counted entity, so its count is positive. COUNT() of a field skips NULL values,
	 * a group whose values are all NULL has the count 0.
	 *
	 * @param QueryCallContext<TType> $context
	 * @param list<list<CriteriaItem<TType>>> $criteriaVariants
	 * @return TType
	 */
	private function getGroupedCountType(QueryCallContext $context, array $criteriaVariants): object
	{
		if ($context->isArgumentOmitted('field')) {
			return $this->types->intRange(1, null);
		}

		$fieldType = $context->getFieldArgumentType('field', $criteriaVariants);

		return $this->types->intRange($fieldType === null || $this->types->canBeNull($fieldType) ? 0 : 1, null);
	}

	/**
	 * The type of the keys asArray() returns. NULL is removed because asArray() throws on it, the validation
	 * reports such calls together with the objects that are kept here, yield() returns them as keys.
	 *
	 * @param TType $type
	 * @return TType
	 */
	private function toKeyType(object $type): object
	{
		return $this->types->toArrayKey($this->types->removeNull($type));
	}

	/**
	 * @param TType|null $type
	 * @return TType
	 */
	private function toPairsKeyType(?object $type): object
	{
		return $type === null || $this->types->isMixed($type) ? $this->types->arrayKey() : $this->toKeyType($type);
	}

	/**
	 * @param class-string $entityClassName
	 * @throws NoClassMetadataException
	 */
	private function getQueryMetadata(string $entityClassName, QueryType $queryType): QueryMetadata
	{
		return new QueryMetadata(
			$this->classMetadataFactory,
			$this->classMetadataFactory->getMetadataFor($entityClassName),
			$queryType,
		);
	}

}
