<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Select\QueryType;

final readonly class ArrayQueriesDynamicReturnTypeExtension extends BaseQueriesDynamicReturnTypeExtension
{

	protected function getQueryType(): QueryType
	{
		return QueryType::Array;
	}

	public function getClass(): string
	{
		return ArrayQueries::class;
	}

}
