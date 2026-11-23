<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Aggregation\AggregateFunction;
use Shredio\DoctrineQueries\Aggregation\SumKind;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\InvalidSelectAliasException;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\Exception\UnsupportedFieldTypeException;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\FieldSelectType;
use Shredio\DoctrineQueries\Select\QueryType;
use Shredio\DoctrineQueries\Select\SelectParser;

/**
 * Validates the calls of the queries: the entity, the fields and associations they refer to and the form of the arguments.
 *
 * @template TType of object
 * @internal
 */
final readonly class QueryCallValidator
{

	private const array RequireNamedArguments = [
		'orderBy' => true,
		'select' => true,
		'joinConfig' => true,
		'pagination' => true,
	];

	/**
	 * @param TypeSystem<TType> $types
	 * @param QueryArgumentReader<TType> $reader
	 * @param CriteriaValueValidator<TType> $criteriaValueValidator
	 */
	public function __construct(
		private TypeSystem $types,
		private AnalysisClassMetadataFactory $classMetadataFactory,
		private QueryArgumentReader $reader,
		private CriteriaValueValidator $criteriaValueValidator,
	)
	{
	}

	/**
	 * @param CallArguments<TType> $arguments
	 * @return list<AnalysisError>
	 */
	public function validate(string $calledOnClass, string $methodName, CallArguments $arguments): array
	{
		$parameters = QueryMethods::getParameters($calledOnClass, $methodName);
		$entityType = $arguments->getType(QueryMethods::EntityPosition, QueryMethods::EntityName);

		if ($parameters === null || $entityType === null) {
			return [];
		}

		/** @var class-string|null $entityClassName checked by the metadata factory */
		$entityClassName = $this->reader->getSingleString($entityType);

		if ($entityClassName === null) {
			return [
				new AnalysisError(
					'The first argument must be a constant string representing the entity class name e.g. `Account::class`.',
					'invalidEntityName',
				),
			];
		}

		try {
			$entityMetadata = $this->classMetadataFactory->getMetadataFor($entityClassName);
		} catch (NoClassMetadataException $exception) {
			return [
				new AnalysisError(
					sprintf('The entity class `%s` does not exist or is not managed by Doctrine.', $exception->entity),
					'invalidEntityClass',
				),
			];
		}

		// QueryType here does not matter, it is only used to validate field names
		$queryMetadata = new QueryMetadata($this->classMetadataFactory, $entityMetadata, QueryType::Array);

		if ($calledOnClass === DoctrineQueries::class && $methodName === 'existsManyBy') {
			return $this->validateExistsManyBy($arguments, $entityClassName, $calledOnClass, $queryMetadata);
		}

		$call = new ValidatedCall($calledOnClass, $methodName, $entityClassName, $queryMetadata);
		$errors = [];

		foreach ($parameters as $argumentPosition => $argumentName) {
			$argument = $arguments->get($argumentPosition, $argumentName);
			if ($argument === null) {
				continue; // Argument is optional, skip if not provided
			}

			$argumentType = $argument->type;

			if (isset(self::RequireNamedArguments[$argumentName]) && $argument->name === null) {
				$errors[] = new AnalysisError(
					sprintf('Argument #%d (%s) must be passed as a named argument. Position is not guaranteed.', $argumentPosition + 1, $argumentName),
					'requireNamedArgument',
				);
			}

			foreach ($this->validateArgument($call, $argumentPosition, $argumentName, $argumentType) as $error) {
				$errors[] = $error;
			}
		}

		return $errors;
	}

	/**
	 * @param TType $argumentType
	 * @return iterable<AnalysisError>
	 */
	private function validateArgument(ValidatedCall $call, int $argumentPosition, string $argumentName, object $argumentType): iterable
	{
		if ($argumentName === 'pagination') {
			return; // only has to be passed as a named argument
		}

		if ($argumentName === 'joinConfig' && $this->types->getScalarKind($argumentType) === ScalarKind::String) {
			return;
		}

		if (in_array($argumentName, ['criteria', 'orderBy', 'select', 'joinConfig', 'preload'], true)) {
			if (!$this->types->isConstantArray($argumentType)) {
				yield new AnalysisError(
					sprintf('Argument #%d must be a constant array representing %s.', $argumentPosition + 1, $argumentName),
					sprintf('invalid%sArgument', ucfirst($argumentName)),
				);

				return;
			}
		}

		if ($argumentName === 'criteria') {
			yield from array_values($this->validateCriteria($call, $argumentType));

			return;
		}

		if ($argumentName === 'orderBy') {
			foreach ($this->reader->getOrderByFields($argumentType) as $field) {
				$error = $this->validateField($call, 'criteria', $field);
				if ($error !== null) {
					yield $error;
				}
			}

			return;
		}

		if ($argumentName === 'select') {
			yield from $this->validateSelect($call, $argumentType);

			return;
		}

		if ($argumentName === 'joinConfig') {
			foreach ($this->reader->getConstantArrayValues($argumentType) as $key => $value) { // $value is 'left'|'inner', no need to validate
				$error = $this->validateAssociation($call, 'joinConfig', new Field((string) $key));
				if ($error !== null) {
					yield $error;
				}
			}

			return;
		}

		if ($argumentName === 'preload') {
			foreach ($this->reader->getPreloadFields($argumentType) as $field) {
				$error = $this->validateAssociation($call, 'preload', $field);
				if ($error !== null) {
					yield $error;
				}
			}

			return;
		}

		// 'field', 'indexField', 'key', 'value'
		if ($this->types->isNull($argumentType)) {
			return; // countBy() without a field
		}

		$fieldName = $this->reader->getSingleString($argumentType);

		if ($fieldName === null) {
			yield new AnalysisError(
				sprintf('Argument #%d must be a constant string representing a field name.', $argumentPosition + 1),
				'invalidFieldArgument',
			);

			return;
		}

		$field = new Field($fieldName);
		if (!$field->getType()->isField()) {
			yield new AnalysisError(
				sprintf(
					'Call to method %s::%s() - argument #%d (%s) must be a single field, the wildcard `%s` selects more of them.',
					$call->className,
					$call->methodName,
					$argumentPosition + 1,
					$argumentName,
					$fieldName,
				),
				'wildcardFieldArgument',
			);

			return;
		}

		$error = $this->validateField($call, 'field', $field);
		if ($error !== null) {
			yield $error;

			return;
		}

		$aggregateFunction = QueryMethods::getAggregateFunction($call->className, $call->methodName);
		if ($argumentName === 'field' && $aggregateFunction !== null) {
			$error = $this->validateAggregatedField($call, $aggregateFunction, $field);
			if ($error !== null) {
				yield $error;
			}
		}
	}

	/**
	 * The aggregated field has to be a field, a summed one a number. The field exists, validateField() checked it.
	 */
	private function validateAggregatedField(ValidatedCall $call, AggregateFunction $function, Field $field): ?AnalysisError
	{
		$fieldMetadata = $call->queryMetadata->getFieldMetadata($field);

		if ($fieldMetadata->isAssociation) {
			return new AnalysisError(
				sprintf(
					'Call to method %s::%s() - `%s` of entity %s is an association and cannot be aggregated, use a field of the related entity instead, e.g. `%s.<field>`.',
					$call->className,
					$call->methodName,
					$field->selector,
					$call->entityClassName,
					$field->selector,
				),
				'aggregatedAssociation',
			);
		}

		if ($function !== AggregateFunction::Sum) {
			return null;
		}

		try {
			$doctrineType = $fieldMetadata->getFieldType()->type;
		} catch (UnsupportedFieldTypeException) {
			return null;
		}

		if (SumKind::canBeSummed($doctrineType) !== false) {
			return null; // also a type that is not registered here, it is not known
		}

		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - the field `%s` of entity %s has the type `%s` and cannot be summed, only integer, decimal and float fields can.',
				$call->className,
				$call->methodName,
				$field->selector,
				$call->entityClassName,
				$doctrineType,
			),
			'unsupportedSum',
		);
	}

	/**
	 * @param TType $selectType
	 * @return iterable<AnalysisError>
	 */
	private function validateSelect(ValidatedCall $call, object $selectType): iterable
	{
		$selectErrorCount = 0;
		foreach ($this->reader->getSelectFields($selectType) as $field) {
			$error = $this->validateField($call, 'select', $field);
			if ($error !== null) {
				yield $error;
				$selectErrorCount++;
			}
		}

		if ($selectErrorCount === 0) {
			foreach ($this->validateSelectAliases($call, $selectType) as $error) {
				yield $error;
				$selectErrorCount++;
			}
		}

		if ($call->className === DoctrineQueries::class && $call->methodName === 'subQuery' && $selectErrorCount === 0) {
			$error = $this->validateSubQuerySelection($call->queryMetadata, $selectType);
			if ($error !== null) {
				yield $error;
			}
		}
	}

	/**
	 * Validates the criteria of fields and, by the same rules, the alternatives of the groups created by Criteria::anyOf().
	 *
	 * @param TType $criteriaType
	 * @return array<string, AnalysisError> Indexed by the message, alternatives often repeat the mistake of another one
	 */
	private function validateCriteria(ValidatedCall $call, object $criteriaType): array
	{
		$errors = [];

		foreach ($this->reader->getCriteria($criteriaType) as $item) {
			if ($this->types->getAnyOfAlternatives($item->valueType) !== null) {
				$error = $this->anyOfComparedWithField($call, $item);
			} else if (CriteriaParser::normalizeOperator($item->operator) === null) {
				$error = $this->unsupportedOperator($call, $item);
			} else if ($call->className === DoctrineQueries::class && $call->methodName === 'deleteBy' && $item->field->hasParent()) {
				$error = $this->deleteByRelatedCriteria($item);
			} else {
				$error = $this->validateField($call, 'criteria', $item->field) ?? $this->criteriaValueValidator->validate($call, $item);
			}

			if ($error !== null) {
				$errors[$error->message] = $error;
			}
		}

		foreach ($this->reader->getCriteriaItemsWithoutField($criteriaType) as [$position, $itemType]) {
			$alternativeTypes = $this->types->getAnyOfAlternatives($itemType);
			if ($alternativeTypes === null) {
				$error = $this->criteriaItemWithoutField($call, $position, $itemType);
				$errors[$error->message] = $error;

				continue;
			}

			if ($alternativeTypes === []) {
				$error = $this->anyOfWithoutAlternatives($call);
				$errors[$error->message] = $error;

				continue;
			}

			foreach ($alternativeTypes as $alternativeType) {
				if (!$this->types->isConstantArray($alternativeType)) {
					$error = $this->nonConstantAlternative($call);
					$errors[$error->message] = $error;

					continue;
				}

				foreach ($this->types->getConstantArrays($alternativeType) as $alternativeShape) {
					if ($alternativeShape->hasOnlyOptionalItems()) {
						$error = $this->alternativeWithoutCriteria($call);
						$errors[$error->message] = $error;
					}
				}

				foreach ($this->validateCriteria($call, $alternativeType) as $message => $error) {
					$errors[$message] = $error;
				}
			}
		}

		return $errors;
	}

	private function validateAssociation(ValidatedCall $call, string $type, Field $field): ?AnalysisError
	{
		try {
			$metadata = $call->queryMetadata->getFieldMetadata($field);
			if (!$metadata->isAssociation) {
				return $this->invalidAssociation($call, $type, $field->getParent() ?? '', $field->name);
			}
		} catch (FieldNotExistsException) {
			return $this->invalidAssociation($call, $type, $field->getParent() ?? '', $field->name);
		} catch (InvalidAssociationPathException $exception) {
			return $this->invalidAssociation($call, $type, $exception->path, $exception->fieldName);
		}

		return null;
	}

	private function validateField(ValidatedCall $call, string $type, Field $field): ?AnalysisError
	{
		if ($field->getType() !== FieldSelectType::Field) {
			return null;
		}

		try {
			$fieldMetadata = $call->queryMetadata->getFieldMetadata($field);
		} catch (FieldNotExistsException) {
			return $this->invalidFieldName($call, $type, $field->name);
		} catch (InvalidAssociationPathException $exception) {
			return $this->invalidAssociation($call, $type, $exception->path, $exception->fieldName);
		}

		if ($fieldMetadata->isToManyAssociation()) {
			return new AnalysisError(
				sprintf(
					'Call to method %s::%s() - `%s` of entity %s is a to-many association, use a field of the related entity instead, e.g. `%s.<field>`.',
					$call->className,
					$call->methodName,
					$field->selector,
					$call->entityClassName,
					$field->selector,
				),
				sprintf('toMany%sAssociation', ucfirst($type)),
			);
		}

		return null;
	}

	/**
	 * @param TType $selectType
	 * @return list<AnalysisError>
	 */
	private function validateSelectAliases(ValidatedCall $call, object $selectType): array
	{
		$selectionErrors = [];
		iterator_to_array(SelectParser::getMetadataForSelection($call->queryMetadata, $this->reader->getSelect($selectType), $selectionErrors), false);

		$errors = [];
		foreach ($selectionErrors ?? [] as $selectionError) {
			if (!$selectionError instanceof InvalidSelectAliasException) {
				continue;
			}

			$errors[] = new AnalysisError(
				sprintf(
					'Call to method %s::%s() - alias `%s` of the selected field `%s` is not a valid identifier, use letters, digits and underscores and do not start with a digit.',
					$call->className,
					$call->methodName,
					$selectionError->alias,
					$selectionError->fieldName,
				),
				'invalidSelectAlias',
			);
		}

		return $errors;
	}

	/**
	 * The sub-query is compared with a single value, so it has to select a single column. An empty selection selects the entity.
	 *
	 * @param TType $selectType
	 */
	private function validateSubQuerySelection(QueryMetadata $queryMetadata, object $selectType): ?AnalysisError
	{
		$select = $this->reader->getSelect($selectType);
		if ($select === []) {
			return null;
		}

		$selectionErrors = []; // invalid fields are reported by the validation of the select argument
		$columnCount = count(iterator_to_array(SelectParser::getMetadataForSelection($queryMetadata, $select, $selectionErrors), false));
		if ($columnCount === 1 || $selectionErrors !== []) {
			return null;
		}

		return new AnalysisError(
			sprintf(
				'Call to method %s::subQuery() - the sub-query selects %d columns, but the value it is compared with needs exactly one.',
				DoctrineQueries::class,
				$columnCount,
			),
			'subQuerySelect',
		);
	}

	/**
	 * @param CallArguments<TType> $arguments
	 * @return list<AnalysisError>
	 */
	private function validateExistsManyBy(CallArguments $arguments, string $entityClassName, string $calledOnClass, QueryMetadata $queryMetadata): array
	{
		$valuesType = $arguments->getType(1, 'values');
		if ($valuesType === null) {
			return []; // covered by other rules
		}

		$rowType = $this->types->getIterableValueType($valuesType);
		$rows = $this->types->getConstantArrays($rowType);

		if ($rows === [] && !$this->types->isNever($rowType)) { // an empty array has no rows to validate
			return [
				new AnalysisError(
					'Argument #2 must be a constant array of constant arrays with constant string keys representing criteria.',
					'invalidCriteriaKey',
				),
			];
		}

		$expectedKeys = null;

		foreach ($rows as $row) {
			$keys = [];
			foreach ($row->items as $item) {
				if (is_string($item->key)) {
					$keys[] = $item->key;
				}
			}

			if ($expectedKeys === null) {
				$expectedKeys = $keys;

				continue;
			}

			// The rows are compared as a whole, the order of their keys does not matter.
			$missingKeys = array_diff($expectedKeys, $keys);
			if ($missingKeys !== []) {
				return [
					new AnalysisError(
						sprintf(
							'Argument #2 must be a constant array of constant arrays with the same keys representing criteria. Missing keys: %s',
							self::formatKeys($missingKeys),
						),
						'invalidCriteriaKeys',
					),
				];
			}

			$unexpectedKeys = array_diff($keys, $expectedKeys);
			if ($unexpectedKeys !== []) {
				return [
					new AnalysisError(
						sprintf(
							'Argument #2 must be a constant array of constant arrays with the same keys representing criteria. Unexpected keys: %s',
							self::formatKeys($unexpectedKeys),
						),
						'invalidCriteriaKeys',
					),
				];
			}
		}

		$call = new ValidatedCall($calledOnClass, 'existsManyBy', $entityClassName, $queryMetadata);
		foreach ($expectedKeys ?? [] as $fieldName) {
			try {
				$field = new Field($fieldName);
				if ($field->hasParent()) { // associations not allowed in existsManyBy
					return [$this->invalidFieldName($call, 'criteria', $fieldName)];
				}

				$queryMetadata->getFieldMetadata($field);
			} catch (FieldNotExistsException|InvalidAssociationPathException) {
				return [$this->invalidFieldName($call, 'criteria', $fieldName)];
			}
		}

		return [];
	}

	/**
	 * @param array<string> $keys
	 */
	private static function formatKeys(array $keys): string
	{
		return implode(', ', array_map(static fn (string $key): string => sprintf('`%s`', $key), $keys));
	}

	/**
	 * @param CriteriaItem<TType> $item
	 */
	private function unsupportedOperator(ValidatedCall $call, CriteriaItem $item): AnalysisError
	{
		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - criteria `%s %s` uses an unsupported operator. Supported operators are: %s.',
				$call->className,
				$call->methodName,
				$item->field->selector,
				$item->operator,
				implode(', ', CriteriaParser::getSupportedOperators()),
			),
			'invalidCriteriaOperator',
		);
	}

	/**
	 * @param CriteriaItem<TType> $item
	 */
	private function anyOfComparedWithField(ValidatedCall $call, CriteriaItem $item): AnalysisError
	{
		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - criteria `%s` is compared with Criteria::anyOf(), pass it among the criteria without a key.',
				$call->className,
				$call->methodName,
				$item->field->selector,
			),
			'invalidCriteriaValue',
		);
	}

	/**
	 * @param TType $itemType
	 */
	private function criteriaItemWithoutField(ValidatedCall $call, int $position, object $itemType): AnalysisError
	{
		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - criteria item %d has no field name, only Criteria::anyOf() can be passed without one, %s given.',
				$call->className,
				$call->methodName,
				$position,
				$this->types->describe($itemType),
			),
			'criteriaItemWithoutField',
		);
	}

	private function anyOfWithoutAlternatives(ValidatedCall $call): AnalysisError
	{
		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - Criteria::anyOf() is called without an alternative, which throws an exception.',
				$call->className,
				$call->methodName,
			),
			'missingCriteriaAlternative',
		);
	}

	private function nonConstantAlternative(ValidatedCall $call): AnalysisError
	{
		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - every alternative of Criteria::anyOf() must be a constant array representing criteria.',
				$call->className,
				$call->methodName,
			),
			'invalidCriteriaAlternative',
		);
	}

	private function alternativeWithoutCriteria(ValidatedCall $call): AnalysisError
	{
		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - an alternative of Criteria::anyOf() can have no criteria, which throws an exception.',
				$call->className,
				$call->methodName,
			),
			'emptyCriteriaAlternative',
		);
	}

	/**
	 * @param CriteriaItem<TType> $item
	 */
	private function deleteByRelatedCriteria(CriteriaItem $item): AnalysisError
	{
		return new AnalysisError(
			sprintf(
				'Call to method %s::deleteBy() - criteria `%s` refers to a related entity, DQL DELETE does not support joins. Filter by the association with a sub-query instead.',
				DoctrineQueries::class,
				$item->field->selector,
			),
			'deleteByRelatedCriteria',
		);
	}

	private function invalidFieldName(ValidatedCall $call, string $type, string $fieldName): AnalysisError
	{
		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - entity %s does not have a field or association named `$%s`.',
				$call->className,
				$call->methodName,
				$call->entityClassName,
				$fieldName,
			),
			sprintf('invalid%sField', ucfirst($type)),
		);
	}

	private function invalidAssociation(ValidatedCall $call, string $type, string $path, string $fieldName): AnalysisError
	{
		return new AnalysisError(
			sprintf(
				'Call to method %s::%s() - entity %s has an invalid association %s. The field `%s` is not an association.',
				$call->className,
				$call->methodName,
				$call->entityClassName,
				$path === '' ? 'in root entity' : sprintf('path `%s`', $path),
				$fieldName,
			),
			sprintf('invalid%sAssociation', ucfirst($type)),
		);
	}

}
