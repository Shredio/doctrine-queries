<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Select\QueryType;

final readonly class ScalarQueriesDynamicReturnTypeExtension extends BaseQueriesDynamicReturnTypeExtension
{

	protected function getQueryType(): QueryType
	{
		return QueryType::Scalar;
	}

	public function getClass(): string
	{
		return ScalarQueries::class;
	}

}
