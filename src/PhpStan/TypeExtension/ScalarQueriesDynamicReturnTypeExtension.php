<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use Shredio\DoctrineQueries\Query\ScalarQueries;

final readonly class ScalarQueriesDynamicReturnTypeExtension extends BaseQueriesDynamicReturnTypeExtension
{

	protected function isScalar(): bool
	{
		return true;
	}

	public function getClass(): string
	{
		return ScalarQueries::class;
	}

}
