<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use Shredio\DoctrineQueries\Query\ScalarQueries;

final readonly class ScalarQueriesDynamicReturnTypeExtension extends BaseQueriesDynamicReturnTypeExtension
{

	public function getClass(): string
	{
		return ScalarQueries::class;
	}

}
