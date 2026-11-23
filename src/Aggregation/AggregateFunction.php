<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Aggregation;

/**
 * The aggregate functions of sumBy(), minBy(), maxBy() and their grouped variants, the value is the DQL function.
 *
 * @internal
 */
enum AggregateFunction: string
{

	case Sum = 'SUM';
	case Min = 'MIN';
	case Max = 'MAX';

}
