<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Select;

enum FieldSelectType
{

	case Field;
	case SelectAll;
	case SelectAllWithRelations;

	public function isField(): bool
	{
		return $this === self::Field;
	}

}
