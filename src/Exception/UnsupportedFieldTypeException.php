<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use LogicException;

/**
 * The field cannot be typed, e.g. a to-many association or a composite foreign key.
 */
final class UnsupportedFieldTypeException extends LogicException
{

}
