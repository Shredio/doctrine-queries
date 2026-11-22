<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\InvalidSelectAliasException;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\PhpStan\CriteriaItemType;
use Shredio\DoctrineQueries\PhpStan\PhpstanClassMetadataFactory;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineService;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineServiceFactory;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\FieldSelectType;
use Shredio\DoctrineQueries\Select\QueryType;
use Shredio\DoctrineQueries\Select\SelectParser;

/**
 * @implements Rule<MethodCall>
 */
final readonly class DoctrineQueriesRule implements Rule
{

	private const array RequireNamedArguments = [
		'orderBy' => true,
		'select' => true,
		'joinConfig' => true,
		'pagination' => true,
	];

	/**
	 * Validated arguments of each method as [position => parameter name]. The names have to match the method
	 * signatures, named arguments are looked up by them. Positions that are not listed (e.g. distinct) are not validated.
	 */
	private const array Map = [
		ScalarQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'pagination', 5 => 'joinConfig'],
			'findOneBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'joinConfig'],
			'findIndexedBy' => [1 => 'indexField', 2 => 'criteria', 3 => 'orderBy', 4 => 'select', 5 => 'joinConfig'],
			'findPairsBy' => [1 => 'key', 2 => 'value', 3 => 'criteria', 4 => 'orderBy', 5 => 'joinConfig'],
			'findColumnValuesBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy', 5 => 'pagination', 6 => 'joinConfig'],
			'findSingleColumnValueBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy', 4 => 'joinConfig'],
		],
		ArrayQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'pagination', 5 => 'joinConfig'],
			'findOneBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'joinConfig'],
			'findIndexedBy' => [1 => 'indexField', 2 => 'criteria', 3 => 'orderBy', 4 => 'select', 5 => 'joinConfig'],
			'findPairsBy' => [1 => 'key', 2 => 'value', 3 => 'criteria', 4 => 'orderBy', 5 => 'joinConfig'],
			'findColumnValuesBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy', 5 => 'pagination', 6 => 'joinConfig'],
			'findSingleColumnValueBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy', 4 => 'joinConfig'],
		],
		ObjectQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'preload', 4 => 'pagination', 5 => 'joinConfig'],
			'findOneBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'joinConfig'],
		],
		DoctrineQueries::class => [
			'countBy' => [1 => 'criteria', 2 => 'field', 4 => 'joinConfig'],
			'existsBy' => [1 => 'criteria'],
			'deleteBy' => [1 => 'criteria'],
			'subQuery' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'joinConfig'],
		],
	];

	private PhpStanDoctrineService $service;

	public function __construct(
		private ObjectMetadataResolver $objectMetadataResolver,
		PhpStanDoctrineServiceFactory $phpStanDoctrineServiceFactory,
	)
	{
		$this->service = $phpStanDoctrineServiceFactory->create();
	}

	public function getNodeType(): string
	{
		return MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$args = $node->getArgs();
		$entityArg = $args[0] ?? null;

		if ($entityArg === null) {
			return [];
		}

		$calledOnType = $scope->getType($node->var);
		$calledOnClass = $calledOnType->getObjectClassNames()[0] ?? null;

		if ($calledOnClass === null) {
			return [];
		}

		$methods = self::Map[$calledOnClass] ?? null;

		if ($methods === null) {
			return [];
		}

		if (!$node->name instanceof Node\Identifier) {
			return [];
		}

		$methodName = $node->name->toString();
		$rules = $methods[$methodName] ?? null;

		if ($rules === null && $methodName !== 'existsManyBy') {
			return [];
		}

		$entityType = $scope->getType($entityArg->value);
		/** @var class-string|null $entityClassName */
		$entityClassName = ($entityType->getConstantStrings()[0] ?? null)?->getValue();

		if ($entityClassName === null) {
			return [
				RuleErrorBuilder::message(
					'The first argument must be a constant string representing the entity class name e.g. `Account::class`.',
				)
					->identifier('doctrineQueries.invalidEntityName')
					->build(),
			];
		}

		try {
			$classMetadataFactory = new PhpstanClassMetadataFactory($this->objectMetadataResolver);
			$entityMetadata = $classMetadataFactory->getMetadataFor($entityClassName);
		} catch (NoClassMetadataException $exception) {
			return [
				RuleErrorBuilder::message(
					sprintf('The entity class `%s` does not exist or is not managed by Doctrine.', $exception->entity),
				)
					->identifier('doctrineQueries.invalidEntityClass')
					->build(),
			];
		}

		$queryMetadata = new QueryMetadata($classMetadataFactory, $entityMetadata, QueryType::Array); // QueryType here does not matter, we only use it to validate field names

		$arguments = [];

		foreach ($args as $i => $arg) {
			$argumentName = $arg->name?->toString();

			if ($argumentName === null) {
				$arguments[$i] = $arg;
			} else {
				$arguments[$argumentName] = $arg;
			}
		}

		if ($rules === null) {
			// special case
			return $this->existManyBy($arguments, $entityClassName, $calledOnClass, $scope, $queryMetadata);
		}

		$errors = [];

		$validateAssociation = function (string $type, Field $field) use ($entityClassName, $methodName, $calledOnClass, $queryMetadata): ?IdentifierRuleError {
			try {
				$metadata = $queryMetadata->getFieldMetadata($field);
				if (!$metadata->isAssociation) {
					return $this->invalidAssociation(
						$type,
						$calledOnClass,
						$methodName,
						$entityClassName,
						$field->getParent() ?? '',
						$field->name,
					);
				}

			} catch (FieldNotExistsException) {
				return $this->invalidAssociation(
					$type,
					$calledOnClass,
					$methodName,
					$entityClassName,
					$field->getParent() ?? '',
					$field->name,
				);
			} catch (InvalidAssociationPathException $exception) {
				return $this->invalidAssociation(
					$type,
					$calledOnClass,
					$methodName,
					$entityClassName,
					$exception->path,
					$exception->fieldName,
				);
			}

			return null;
		};
		$validateField = function (string $type, Field $field) use ($entityClassName, $methodName, $calledOnClass, $queryMetadata): ?IdentifierRuleError {
			if ($field->getType() !== FieldSelectType::Field) {
				return null;
			}

			try {
				$fieldMetadata = $queryMetadata->getFieldMetadata($field);
			} catch (FieldNotExistsException) {
				return $this->invalidFieldName(
					$type,
					$calledOnClass,
					$methodName,
					$entityClassName,
					$field->name,
				);
			} catch (InvalidAssociationPathException $exception) {
				return $this->invalidAssociation(
					$type,
					$calledOnClass,
					$methodName,
					$entityClassName,
					$exception->path,
					$exception->fieldName,
				);
			}

			if ($fieldMetadata->isToManyAssociation()) {
				return RuleErrorBuilder::message(
					sprintf(
						'Call to method %s::%s() - `%s` of entity %s is a to-many association, use a field of the related entity instead, e.g. `%s.<field>`.',
						$calledOnClass,
						$methodName,
						$field->selector,
						$entityClassName,
						$field->selector,
					),
				)
					->identifier(sprintf('doctrineQueries.toMany%sAssociation', ucfirst($type)))
					->build();
			}

			return null;
		};

		foreach ($rules as $argumentPos => $argumentName) {
			$arg = $arguments[$argumentName] ?? $arguments[$argumentPos] ?? null;
			if ($arg === null) {
				continue; // Argument is optional, skip if not provided
			}

			$argType = $scope->getType($arg->value);

			if (isset(self::RequireNamedArguments[$argumentName])) {
				if ($arg->name === null) {
					$errors[] = RuleErrorBuilder::message(
						sprintf('Argument #%d (%s) must be passed as a named argument. Position is not guaranteed.', $argumentPos + 1, $argumentName),
					)
						->identifier('doctrineQueries.requireNamedArgument')
						->build();
				}
			}

			if ($argumentName === 'criteria') {
				$constantArrayError = $this->validateConstantArray($argType, $argumentPos, 'criteria');
				if ($constantArrayError !== null) {
					$errors[] = $constantArrayError;
					continue;
				}

				foreach ($this->service->getCriteriaFromType($argType) as $item) {
					if (CriteriaParser::normalizeOperator($item->operator) === null) {
						$errors[] = $this->unsupportedOperator($calledOnClass, $methodName, $item);

						continue;
					}

					if ($calledOnClass === DoctrineQueries::class && $methodName === 'deleteBy' && $item->field->hasParent()) {
						$errors[] = $this->deleteByRelatedCriteria($item);

						continue;
					}

					$error = $validateField('criteria', $item->field);
					if ($error !== null) {
						$errors[] = $error;
					}
				}

				continue;
			}

			if ($argumentName === 'pagination') {
				continue; // only has to be passed as a named argument
			}

			if ($argumentName === 'orderBy') {
				$constantArrayError = $this->validateConstantArray($argType, $argumentPos, 'orderBy');
				if ($constantArrayError !== null) {
					$errors[] = $constantArrayError;
					continue;
				}

				foreach ($this->service->getFieldsFromOrderByType($argType) as $field) {
					$error = $validateField('criteria', $field);
					if ($error !== null) {
						$errors[] = $error;
					}
				}

				continue;
			}

			if ($argumentName === 'select') {
				$constantArrayError = $this->validateConstantArray($argType, $argumentPos, 'select');
				if ($constantArrayError !== null) {
					$errors[] = $constantArrayError;
					continue;
				}

				$selectErrorCount = 0;
				foreach ($this->service->getFieldsFromSelectArrayType($argType) as [$field]) {
					$error = $validateField('select', $field);
					if ($error !== null) {
						$errors[] = $error;
						$selectErrorCount++;
					}
				}

				if ($selectErrorCount === 0) {
					foreach ($this->validateSelectAliases($calledOnClass, $methodName, $queryMetadata, $argType) as $error) {
						$errors[] = $error;
						$selectErrorCount++;
					}
				}

				if ($calledOnClass === DoctrineQueries::class && $methodName === 'subQuery' && $selectErrorCount === 0) {
					$error = $this->validateSubQuerySelection($queryMetadata, $argType);
					if ($error !== null) {
						$errors[] = $error;
					}
				}

				continue;
			}

			if ($argumentName === 'joinConfig') {
				if ($argType->isString()->yes()) {
					continue;
				}

				$constantArrayError = $this->validateConstantArray($argType, $argumentPos, 'joinConfig');
				if ($constantArrayError !== null) {
					$errors[] = $constantArrayError;
					continue;
				}

				foreach ($this->service->getArrayFromConstantArray($argType) as $key => $value) { // $value is 'left'|'inner', no need to validate
					$error = $validateAssociation('joinConfig', new Field($key));
					if ($error !== null) {
						$errors[] = $error;
					}
				}

				continue;
			}

			if ($argumentName === 'preload') {
				$constantArrayError = $this->validateConstantArray($argType, $argumentPos, 'preload');
				if ($constantArrayError !== null) {
					$errors[] = $constantArrayError;
					continue;
				}

				foreach ($this->service->getFieldsFromPreloadType($argType) as $field) {
					$error = $validateAssociation('preload', $field);
					if ($error !== null) {
						$errors[] = $error;
					}
				}

				continue;
			}

			// 'field', 'indexField', 'key', 'value'
			if ($argType->isNull()->yes()) {
				continue; // countBy() without a field
			}

			$fieldName = $this->service->tryGetSingleStringFromType($argType);

			if ($fieldName === null) {
				$errors[] = RuleErrorBuilder::message(
					sprintf('Argument #%d must be a constant string representing a field name.', $argumentPos + 1),
				)
					->identifier('doctrineQueries.invalidFieldArgument')
					->build();

				continue;
			}

			$field = new Field($fieldName);
			if (!$field->getType()->isField()) {
				$errors[] = RuleErrorBuilder::message(
					sprintf(
						'Call to method %s::%s() - argument #%d (%s) must be a single field, the wildcard `%s` selects more of them.',
						$calledOnClass,
						$methodName,
						$argumentPos + 1,
						$argumentName,
						$fieldName,
					),
				)
					->identifier('doctrineQueries.wildcardFieldArgument')
					->build();

				continue;
			}

			$error = $validateField('field', $field);
			if ($error !== null) {
				$errors[] = $error;
			}
		}

		return $errors;
	}

	private function validateConstantArray(Type $argType, int $argumentPos, string $argumentName): ?IdentifierRuleError
	{
		if (!$argType->isConstantArray()->yes()) {
			return RuleErrorBuilder::message(
				sprintf('Argument #%d must be a constant array representing %s.', $argumentPos + 1, $argumentName),
			)
				->identifier(sprintf('doctrineQueries.invalid%sArgument', ucfirst($argumentName)))
				->build();
		}

		return null;
	}

	/**
	 * @return list<IdentifierRuleError>
	 */
	private function validateSelectAliases(string $calledOnClass, string $methodName, QueryMetadata $queryMetadata, Type $selectType): array
	{
		$selectionErrors = [];
		iterator_to_array(SelectParser::getMetadataForSelection($queryMetadata, $this->service->getSelectFromType($selectType), $selectionErrors), false);

		$errors = [];
		foreach ($selectionErrors ?? [] as $selectionError) {
			if (!$selectionError instanceof InvalidSelectAliasException) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'Call to method %s::%s() - alias `%s` of the selected field `%s` is not a valid identifier, use letters, digits and underscores and do not start with a digit.',
					$calledOnClass,
					$methodName,
					$selectionError->alias,
					$selectionError->fieldName,
				),
			)
				->identifier('doctrineQueries.invalidSelectAlias')
				->build();
		}

		return $errors;
	}

	/**
	 * The sub-query is compared with a single value, so it has to select a single column. An empty selection selects the entity.
	 */
	private function validateSubQuerySelection(QueryMetadata $queryMetadata, Type $selectType): ?IdentifierRuleError
	{
		$select = $this->service->getSelectFromType($selectType);
		if ($select === []) {
			return null;
		}

		$selectionErrors = []; // invalid fields are reported by the validation of the select argument
		$columnCount = count(iterator_to_array(SelectParser::getMetadataForSelection($queryMetadata, $select, $selectionErrors), false));
		if ($columnCount === 1 || $selectionErrors !== []) {
			return null;
		}

		return RuleErrorBuilder::message(
			sprintf(
				'Call to method %s::subQuery() - the sub-query selects %d columns, but the value it is compared with needs exactly one.',
				DoctrineQueries::class,
				$columnCount,
			),
		)
			->identifier('doctrineQueries.subQuerySelect')
			->build();
	}

	private function unsupportedOperator(string $calledOnClass, string $methodName, CriteriaItemType $item): IdentifierRuleError
	{
		return RuleErrorBuilder::message(
			sprintf(
				'Call to method %s::%s() - criteria `%s %s` uses an unsupported operator. Supported operators are: %s.',
				$calledOnClass,
				$methodName,
				$item->field->selector,
				$item->operator,
				implode(', ', CriteriaParser::getSupportedOperators()),
			),
		)
			->identifier('doctrineQueries.invalidCriteriaOperator')
			->build();
	}

	private function deleteByRelatedCriteria(CriteriaItemType $item): IdentifierRuleError
	{
		return RuleErrorBuilder::message(
			sprintf(
				'Call to method %s::deleteBy() - criteria `%s` refers to a related entity, DQL DELETE does not support joins. Filter by the association with a sub-query instead.',
				DoctrineQueries::class,
				$item->field->selector,
			),
		)
			->identifier('doctrineQueries.deleteByRelatedCriteria')
			->build();
	}

	private function invalidFieldName(string $type, string $calledOnClass, string $methodName, string $entityClassName, string $fieldName): IdentifierRuleError
	{
		return RuleErrorBuilder::message(
			sprintf(
				'Call to method %s::%s() - entity %s does not have a field or association named `$%s`.',
				$calledOnClass,
				$methodName,
				$entityClassName,
				$fieldName,
			),
		)
			->identifier(sprintf('doctrineQueries.invalid%sField', ucfirst($type)))
			->build();
	}

	private function invalidAssociation(string $type, string $calledOnClass, string $methodName, string $entityClassName, string $path, string $fieldName): IdentifierRuleError
	{
		return RuleErrorBuilder::message(
			sprintf(
				'Call to method %s::%s() - entity %s has an invalid association %s. The field `%s` is not an association.',
				$calledOnClass,
				$methodName,
				$entityClassName,
				$path === '' ? 'in root entity' : sprintf('path `%s`', $path),
				$fieldName,
			),
		)
			->identifier(sprintf('doctrineQueries.invalid%sAssociation', ucfirst($type)))
			->build();
	}

	/**
	 * Special validation for existsManyBy method.
	 *
	 * @param array<int|string, Node\Arg> $args
	 * @return list<IdentifierRuleError>
	 */
	private function existManyBy(array $args, string $entityClassName, string $calledOnClass, Scope $scope, QueryMetadata $queryMetadata): array
	{
		$arg = $args[1] ?? $args['values'] ?? null;
		if ($arg === null) {
			return []; // covered by other rules
		}

		$valueType = $scope->getType($arg->value)->getIterableValueType();
		$constantArrays = $valueType->getConstantArrays();

		if ($constantArrays === [] && !$valueType instanceof NeverType) { // an empty array has no rows to validate
			return [
				RuleErrorBuilder::message(
					'Argument #2 must be a constant array of constant arrays with constant string keys representing criteria.',
				)
					->identifier('doctrineQueries.invalidCriteriaKey')
					->build(),
			];
		}

		$expectedKeys = null;

		foreach ($constantArrays as $constantArray) {
			$keys = [];
			foreach ($constantArray->getKeyTypes() as $keyType) {
				foreach ($keyType->getConstantStrings() as $constantString) {
					$keys[] = $constantString->getValue();
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
					RuleErrorBuilder::message(
						sprintf(
							'Argument #2 must be a constant array of constant arrays with the same keys representing criteria. Missing keys: %s',
							implode(', ', array_map(static fn (string $key): string => sprintf('`%s`', $key), $missingKeys)),
						),
					)
						->identifier('doctrineQueries.invalidCriteriaKeys')
						->build(),
				];
			}

			$unexpectedKeys = array_diff($keys, $expectedKeys);
			if ($unexpectedKeys !== []) {
				return [
					RuleErrorBuilder::message(
						sprintf(
							'Argument #2 must be a constant array of constant arrays with the same keys representing criteria. Unexpected keys: %s',
							implode(', ', array_map(static fn (string $key): string => sprintf('`%s`', $key), $unexpectedKeys)),
						),
					)
						->identifier('doctrineQueries.invalidCriteriaKeys')
						->build(),
				];
			}
		}

		foreach ($expectedKeys ?? [] as $fieldName) {
			try {
				$field = new Field($fieldName);
				if ($field->hasParent()) { // associations not allowed in existsManyBy
					return [
						$this->invalidFieldName('criteria', $calledOnClass, 'existsManyBy', $entityClassName, $fieldName),
					];
				}

				$queryMetadata->getFieldMetadata($field);
			} catch (FieldNotExistsException|InvalidAssociationPathException) {
				return [
					$this->invalidFieldName('criteria', $calledOnClass, 'existsManyBy', $entityClassName, $fieldName),
				];
			}
		}

		return [];
	}

}
