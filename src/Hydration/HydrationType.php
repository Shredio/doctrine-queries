<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Hydration;

enum HydrationType
{

	case Array;
	case Object;
	case Scalar;

	public function getDefaultValueForWithRelations(): bool
	{
		return match ($this) {
			self::Array, self::Scalar => false,
			self::Object => true,
		};
	}

}
