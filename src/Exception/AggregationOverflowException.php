<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use OverflowException;

/**
 * The sum of an integer field does not fit into a PHP integer.
 */
final class AggregationOverflowException extends OverflowException
{

}
