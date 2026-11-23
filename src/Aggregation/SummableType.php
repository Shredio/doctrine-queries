<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Aggregation;

/**
 * Implemented by a custom DBAL type whose fields can be summed by sumBy() and sumGroupedBy().
 *
 * The sum is converted by convertToPHPValue() of the type, as a value of the field is, so it has the PHP type
 * of the field. convertToPHPValue() has to accept what the databases return for SUM(): a numeric string, an integer
 * or a float, and it has to return a number (an integer, a float or a numeric string).
 *
 * @api
 */
interface SummableType
{

}
