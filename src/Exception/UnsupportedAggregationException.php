<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use LogicException;

/**
 * The field cannot be aggregated the requested way, e.g. a sum of a string field or of an association.
 */
final class UnsupportedAggregationException extends LogicException
{

}
