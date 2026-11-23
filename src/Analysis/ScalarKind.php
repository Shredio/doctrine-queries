<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

enum ScalarKind
{

	case Int;
	case Float;
	case Bool;
	case String;

}
