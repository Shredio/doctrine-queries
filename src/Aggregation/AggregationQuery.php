<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Aggregation;

use Doctrine\ORM\QueryBuilder;

/**
 * @internal
 */
final readonly class AggregationQuery
{

	public function __construct(
		public QueryBuilder $queryBuilder,
		public AggregatedValueConverter $converter,
	)
	{
	}

}
