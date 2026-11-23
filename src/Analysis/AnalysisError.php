<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

/**
 * A mistake in a call of the queries, an extension reports it the way of its static analyser.
 */
final readonly class AnalysisError
{

	/**
	 * @param non-empty-string $identifier In camelCase without a prefix, e.g. invalidCriteriaOperator
	 */
	public function __construct(
		public string $message,
		public string $identifier,
	)
	{
	}

}
