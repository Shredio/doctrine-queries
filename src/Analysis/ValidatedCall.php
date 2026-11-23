<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Metadata\QueryMetadata;

/**
 * @internal
 */
final readonly class ValidatedCall
{

	public function __construct(
		public string $className,
		public string $methodName,
		public string $entityClassName,
		public QueryMetadata $queryMetadata,
	)
	{
	}

}
