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
 * Reports asArray() of findPairsBy(), findIndexedBy() and the grouped aggregations (countGroupedBy(), sumGroupedBy(),
 * minGroupedBy(), maxGroupedBy()) whose key can be NULL or a value PHP arrays do not accept as a key (e.g. an enum or a date hydrated by ArrayQueries), asArray() throws on such a row.
 *
 * Only calls chained directly on the finder (optionally through setLimit() and setOffset()) are checked,
 * a result stored in a variable is not followed. yield() is never reported, it accepts any key.
 *
 * @implements Rule<MethodCall>
 */
final readonly class ResultArrayKeyRule implements Rule
{

	private const array ResultModifiers = [
		'setLimit' => true,
		'setOffset' => true,
	];

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

		$finderMethod = $finderCall->name->toString();
		$finderClass = $scope->getType($finderCall->var)->getObjectClassNames()[0] ?? null;
		if ($finderClass === null || !DoctrineQueriesAnalyzer::validatesResultArrayKey($finderClass, $finderMethod)) {
			return [];
		}

		return PhpStanRuleErrors::create($this->analyzer->validateResultArrayKey(
			$finderClass,
			$finderMethod,
			PhpStanAnalyzerFactory::createArguments($finderCall->getArgs(), $scope),
		));
	}

}
