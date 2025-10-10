<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Parameter;

use Doctrine\ORM\Query\Parameter;

interface DoctrineParameterAware
{

	public function createDoctrineParameter(string|int $key): Parameter;

}
