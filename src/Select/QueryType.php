<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Select;

enum QueryType
{

	case Array;
	case Scalar;
	case Object;

	public function isAliasesRequired(): bool
	{
		return $this === self::Scalar;
	}

}
