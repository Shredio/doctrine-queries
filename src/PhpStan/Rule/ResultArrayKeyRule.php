<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use Shredio\DoctrineQueries\Exception\NoClassMetadataException;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineService;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineServiceFactory;
use Shredio\DoctrineQueries\PhpStan\TypeExtension\DynamicReturnTypeContext;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * Reports findPairsBy()->asArray() and findIndexedBy()->asArray() whose key can be NULL or a value PHP arrays
 * do not accept as a key (e.g. an enum or a date hydrated by ArrayQueries), asArray() throws on such a row.
 *
 * Only calls chained directly on the finder (optionally through setLimit() and setOffset()) are checked,
 * a result stored in a variable is not followed. yield() is never reported, it accepts any key.
 *
 * @implements Rule<MethodCall>
 */
final readonly class ResultArrayKeyRule implements Rule
{

	/**
	 * Positions of the arguments in the method signatures as [key field, criteria, joinConfig].
	 */
	private const array Methods = [
		'findPairsBy' => [1, 3, 5],
		'findIndexedBy' => [1, 2, 5],
	];

	private const array QueryTypes = [
		ScalarQueries::class => QueryType::Scalar,
		ArrayQueries::class => QueryType::Array,
	];

	private const array ResultModifiers = [
		'setLimit' => true,
		'setOffset' => true,
	];

	private PhpStanDoctrineService $service;

	public function __construct(PhpStanDoctrineServiceFactory $phpStanDoctrineServiceFactory)
	{
		$this->service = $phpStanDoctrineServiceFactory->create();
	}

	public function getNodeType(): string
	{
		return MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!$node->name instanceof Node\Identifier || $node->name->toString() !== 'asArray') {
			return [];
		}

		$finderCall = $node->var;
		while ($finderCall instanceof MethodCall && $finderCall->name instanceof Node\Identifier && isset(self::ResultModifiers[$finderCall->name->toString()])) {
			$finderCall = $finderCall->var;
		}

		if (!$finderCall instanceof MethodCall || !$finderCall->name instanceof Node\Identifier) {
			return [];
		}

		$methodName = $finderCall->name->toString();
		$positions = self::Methods[$methodName] ?? null;
		if ($positions === null) {
			return [];
		}

		$calledOnType = $scope->getType($finderCall->var);
		$calledOnClass = $calledOnType->getObjectClassNames()[0] ?? null;
		if ($calledOnClass === null) {
			return [];
		}

		$queryType = self::QueryTypes[$calledOnClass] ?? null;
		if ($queryType === null) {
			return [];
		}

		$methodReflection = $scope->getMethodReflection($calledOnType, $methodName);
		if ($methodReflection === null) {
			return [];
		}

		// Named arguments are moved to their positions, the same way PHPStan passes the call to the return type extension.
		$parametersAcceptor = ParametersAcceptorSelector::selectFromArgs($scope, $finderCall->getArgs(), $methodReflection->getVariants());
		$args = ArgumentsNormalizer::reorderMethodArguments($parametersAcceptor, $finderCall)?->getArgs() ?? [];
		[$keyPosition, $criteriaPosition, $joinConfigPosition] = $positions;
		if (!isset($args[0], $args[$keyPosition])) {
			return [];
		}

		$entityClassName = $this->service->tryGetSingleStringFromType($scope->getType($args[0]->value));
		$keyField = $this->service->tryGetSingleStringFromType($scope->getType($args[$keyPosition]->value));
		if ($entityClassName === null || $keyField === null || !class_exists($entityClassName)) {
			return []; // reported by DoctrineQueriesRule
		}

		try {
			$context = new DynamicReturnTypeContext($args, $scope, $entityClassName, $queryType, $this->service);
		} catch (NoClassMetadataException) {
			return []; // reported by DoctrineQueriesRule
		}

		$keyType = $context->tryToCreateTypeFromConstantType($keyPosition, $context->getCriteria($criteriaPosition), $joinConfigPosition);
		if ($keyType instanceof MixedType) {
			return []; // an unknown field is reported by DoctrineQueriesRule, an unknown type cannot be judged
		}

		$keyTypeWithoutNull = TypeCombinator::removeNull($keyType);
		if (!(new UnionType([new IntegerType(), new StringType()]))->isSuperTypeOf($keyTypeWithoutNull)->yes()) {
			return [
				RuleErrorBuilder::message(
					sprintf(
						'Call to method %s::%s() - the key `%s` is returned as %s, but asArray() accepts only integers and strings as keys.%s Iterate over yield() to keep the keys as they are.',
						$calledOnClass,
						$methodName,
						$keyField,
						$keyTypeWithoutNull->describe(VerbosityLevel::typeOnly()),
						$queryType === QueryType::Array ? sprintf(' Use %s to get the database values.', ScalarQueries::class) : '',
					),
				)
					->identifier('doctrineQueries.invalidArrayKey')
					->build(),
			];
		}

		if (TypeCombinator::containsNull($keyType)) {
			return [
				RuleErrorBuilder::message(
					sprintf(
						'Call to method %s::%s() - the key `%s` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'%s !=\' => null], or iterate over yield().',
						$calledOnClass,
						$methodName,
						$keyField,
						$keyField,
					),
				)
					->identifier('doctrineQueries.nullableArrayKey')
					->build(),
			];
		}

		return [];
	}

}
