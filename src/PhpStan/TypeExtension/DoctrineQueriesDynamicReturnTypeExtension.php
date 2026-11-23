<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use Shredio\DoctrineQueries\DoctrineQueries;

final readonly class DoctrineQueriesDynamicReturnTypeExtension extends BaseQueriesDynamicReturnTypeExtension
{

	public function getClass(): string
	{
		return DoctrineQueries::class;
	}

}
