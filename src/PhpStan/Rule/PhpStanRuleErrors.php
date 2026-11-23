<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\Rule;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shredio\DoctrineQueries\Analysis\AnalysisError;

/**
 * @internal
 */
final readonly class PhpStanRuleErrors
{

	private const string IdentifierPrefix = 'doctrineQueries';

	/**
	 * @param list<AnalysisError> $errors
	 * @return list<IdentifierRuleError>
	 */
	public static function create(array $errors): array
	{
		return array_map(
			static fn (AnalysisError $error): IdentifierRuleError => RuleErrorBuilder::message($error->message)
				->identifier(sprintf('%s.%s', self::IdentifierPrefix, $error->identifier))
				->build(),
			$errors,
		);
	}

}
