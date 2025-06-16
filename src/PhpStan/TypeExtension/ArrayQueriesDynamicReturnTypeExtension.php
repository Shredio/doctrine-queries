<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use Shredio\DoctrineQueries\Query\ArrayQueries;

final readonly class ArrayQueriesDynamicReturnTypeExtension extends BaseQueriesDynamicReturnTypeExtension
{

	protected function isScalar(): bool
	{
		return false;
	}

	public function getClass(): string
	{
		return ArrayQueries::class;
	}

}
