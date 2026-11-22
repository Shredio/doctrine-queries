<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Select;

enum QueryType
{

	case Array;
	case Scalar;
	case Object;
	/**
	 * Selection of a sub-query used inside an IN() expression. DQL does not accept aliases on path expressions
	 * in a sub-select, so fields are selected without them.
	 */
	case SubQuery;

	public function isAliasesRequired(): bool
	{
		return $this === self::Scalar;
	}

}
