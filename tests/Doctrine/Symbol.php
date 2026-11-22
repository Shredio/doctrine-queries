<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Doctrine\ORM\Query\Parameter;
use Shredio\DoctrineQueries\Parameter\DoctrineParameterAware;

final readonly class Symbol implements DoctrineParameterAware
{

	public function __construct(
		public string $value,
	)
	{
	}

	/**
	 * Doctrine cannot infer a custom DBAL type from a PHP object, the value object tells it.
	 */
	public function createDoctrineParameter(string|int $key): Parameter
	{
		return new Parameter($key, $this, SymbolType::Name);
	}

}
