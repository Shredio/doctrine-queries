<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\QueryBuilder;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;

/**
 * @internal
 */
final readonly class SubQuery
{

	/** @var callable(QueryMetadata $metadata): QueryBuilder */
	private mixed $factory;

	/**
	 * @param callable(QueryMetadata $metadata): QueryBuilder $factory
	 */
	public function __construct(
		callable $factory,
	)
	{
		$this->factory = $factory;
	}

	public function build(QueryMetadata $metadata): QueryBuilder
	{
		return ($this->factory)($metadata);
	}

}
