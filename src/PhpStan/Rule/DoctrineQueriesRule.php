<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Type;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\PhpStan\PhpstanClassMetadataFactory;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineService;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineServiceFactory;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * @implements Rule<MethodCall>
 */
final readonly class DoctrineQueriesRule implements Rule
{

	private const array Map = [
		ScalarQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'joinConfig'],
			'findOneBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'joinConfig'],
			'findIndexedBy' => [1 => 'indexField', 2 => 'criteria', 3 => 'orderBy', 4 => 'select', 5 => 'joinConfig'],
			'findPairsBy' => [1 => 'field', 2 => 'field', 3 => 'criteria', 4 => 'orderBy', 5 => 'joinConfig'],
			'findColumnValuesBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy', 5 => 'joinConfig'],
			'findSingleColumnValueBy' => [1 => 'field', 2 => 'criteria'],
		],
		ArrayQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'joinConfig'],
			'findOneBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'joinConfig'],
			'findIndexedBy' => [1 => 'indexField', 2 => 'criteria', 3 => 'orderBy', 4 => 'select', 5 => 'joinConfig'],
			'findPairsBy' => [1 => 'field', 2 => 'field', 3 => 'criteria', 4 => 'orderBy', 5 => 'joinConfig'],
			'findColumnValuesBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy', 5 => 'joinConfig'],
			'findSingleColumnValueBy' => [1 => 'field', 2 => 'criteria'],
		],
		ObjectQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy'],
			'findOneBy' => [1 => 'criteria', 2 => 'orderBy'],
		],
		DoctrineQueries::class => [
			'countBy' => [1 => 'criteria'],
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

		if ($rules === null) {
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
			return [$exception->toPhpstanError()];
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

		$errors = [];

		foreach ($rules as $argumentPos => $argumentName) {
			$arg = $arguments[$argumentName] ?? $arguments[$argumentPos] ?? null;
			if ($arg === null) {
				continue; // Argument is optional, skip if not provided
			}

			$argType = $scope->getType($arg->value);

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
				try {
					$queryMetadata->getFieldMetadata($field);
				} catch (FieldNotExistsException) {
					return $this->invalidFieldName(
						$type,
						$calledOnClass,
						$methodName,
						$entityClassName,
						$field->name
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

			if ($argumentName === 'criteria') {
				$constantArrayError = $this->validateConstantArray($argType, $argumentPos, 'criteria');
				if ($constantArrayError !== null) {
					$errors[] = $constantArrayError;
					continue;
				}

				foreach ($this->service->getCriteriaFromType($argType) as $item) {
					$error = $validateField('criteria', $item->field);
					if ($error !== null) {
						$errors[] = $error;
					}
				}

				continue;
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

				foreach ($this->service->getFieldsFromSelectArrayType($argType) as [$field]) {
					$error = $validateField('select', $field);
					if ($error !== null) {
						$errors[] = $error;
					}
				}

				continue;
			}

			if ($argumentName === 'joinConfig') {
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

			// 'field', 'indexField'
			$fieldName = $this->service->tryGetSingleStringFromType($argType);

			if ($fieldName === null) {
				$errors[] = RuleErrorBuilder::message(
					sprintf('Argument #%d must be a constant string representing a field name.', $argumentPos + 1),
				)
					->identifier('doctrineQueries.invalidFieldArgument')
					->build();

				continue;
			}

			$error = $validateField('field', new Field($fieldName));
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

}
