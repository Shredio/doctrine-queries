<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\Type;
use Shredio\DoctrineQueries\Analysis\DoctrineQueriesAnalyzer;
use Shredio\DoctrineQueries\PhpStan\PhpStanAnalyzerFactory;

/**
 * @implements Rule<MethodCall>
 */
final readonly class DoctrineQueriesRule implements Rule
{

	/** @var DoctrineQueriesAnalyzer<Type> */
	private DoctrineQueriesAnalyzer $analyzer;

	public function __construct(PhpStanAnalyzerFactory $analyzerFactory)
	{
		$this->analyzer = $analyzerFactory->create();
	}

	public function getNodeType(): string
	{
		return MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!$node->name instanceof Node\Identifier) {
			return [];
		}

		$calledOnClass = $scope->getType($node->var)->getObjectClassNames()[0] ?? null;
		$methodName = $node->name->toString();

		if ($calledOnClass === null || !DoctrineQueriesAnalyzer::validatesCall($calledOnClass, $methodName)) {
			return [];
		}

		return PhpStanRuleErrors::create($this->analyzer->validateCall(
			$calledOnClass,
			$methodName,
			PhpStanAnalyzerFactory::createArguments($node->getArgs(), $scope),
		));
	}

}
