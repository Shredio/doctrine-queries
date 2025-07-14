<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\QueryBuilder;

final readonly class SubQuery
{

	/** @var callable(string $alias): QueryBuilder */
	private mixed $factory;

	/**
	 * @param callable(string $alias): QueryBuilder $factory
	 */
	public function __construct(
		callable $factory,
	)
	{
		$this->factory = $factory;
	}

	public function build(string $alias): QueryBuilder
	{
		return ($this->factory)($alias);
	}

}
