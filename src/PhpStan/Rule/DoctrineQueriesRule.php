<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineService;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineServiceFactory;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;

/**
 * @implements Rule<MethodCall>
 */
final readonly class DoctrineQueriesRule implements Rule
{

	private const Map = [
		ScalarQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select'],
			'findByWithRelations' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select'],
			'findPairsBy' => [1 => 'field', 2 => 'field', 3 => 'criteria', 4 => 'orderBy'],
			'findColumnValuesBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy'],
			'findSingleColumnValueBy' => [1 => 'field', 2 => 'criteria'],
		],
		ArrayQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select'],
			'findByWithRelations' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select'],
			'findPairsBy' => [1 => 'field', 2 => 'field', 3 => 'criteria', 4 => 'orderBy'],
			'findColumnValuesBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy'],
			'findSingleColumnValueBy' => [1 => 'field', 2 => 'criteria'],
		],
		ObjectQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy'],
		],
		DoctrineQueries::class => [
			'countBy' => [1 => 'criteria'],
			'existsBy' => [1 => 'criteria'],
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

		$classMetadata = $this->objectMetadataResolver->getClassMetadata($entityClassName);
		if ($classMetadata === null) {
			return [
				RuleErrorBuilder::message(
					sprintf('The entity class `%s` does not exist or is not managed by Doctrine.', $entityClassName),
				)
					->identifier('doctrineQueries.invalidEntityClass')
					->build(),
			];
		}

		$entityService = $this->service->getEntityServiceFor($entityClassName);

		foreach ($rules as $argumentPos => $rule) {
			$arg = $args[$argumentPos];
			$argType = $scope->getType($arg->value);

			if ($rule === 'criteria') {
				if (!$argType->isConstantArray()->yes()) {
					return [
						RuleErrorBuilder::message(
							sprintf('Argument #%d must be a constant array representing criteria.', $argumentPos + 1),
						)
							->identifier('doctrineQueries.invalidCriteriaArgument')
							->build(),
					];
				}

				foreach ($this->service->getCriteriaFromType($argType) as $item) {
					if (!$entityService->hasFieldOrAssociation($item->fieldName)) {
						return $this->invalidFieldName('criteria', $calledOnClass, $methodName, $entityClassName, $item->fieldName);
					}
				}

				continue;
			}

			if ($rule === 'orderBy') {
				foreach ($this->service->getFieldsFromOrderByType($argType) as $field) {
					if (!$entityService->hasFieldOrAssociation($field)) {
						return $this->invalidFieldName('orderBy', $calledOnClass, $methodName, $entityClassName, $field);
					}
				}

				continue;
			}

			if ($rule === 'select') {
				foreach ($this->service->getFieldsFromSelectArrayType($argType) as [$fieldName]) {
					if (!$entityService->hasFieldOrAssociation($fieldName)) {
						return $this->invalidFieldName('select', $calledOnClass, $methodName, $entityClassName, $fieldName);
					}
				}
 			}

			if ($rule === 'field') {
				$fieldName = $this->service->tryGetSingleStringFromType($argType);

				if ($fieldName === null) {
					return [
						RuleErrorBuilder::message(
							sprintf('Argument #%d must be a constant string representing a field name.', $argumentPos + 1),
						)
							->identifier('doctrineQueries.invalidFieldArgument')
							->build(),
					];
				}

				if (!$entityService->hasFieldOrAssociation($fieldName)) {
					return $this->invalidFieldName('field', $calledOnClass, $methodName, $entityClassName, $fieldName);
				}
			}
		}

		return [];
	}

	/**
	 * @return list<IdentifierRuleError>
	 */
	private function invalidFieldName(string $type, string $calledOnClass, string $methodName, string $entityClassName, string $fieldName): array
	{
		return [
			RuleErrorBuilder::message(
				sprintf(
					'Call to method %s::%s() - entity %s does not have a field or association named `$%s`.',
					$calledOnClass,
					$methodName,
					$entityClassName,
					$fieldName,
				),
			)
				->identifier(sprintf('doctrineQueries.invalid%sField', ucfirst($type)))
				->build(),
		];
	}

}
