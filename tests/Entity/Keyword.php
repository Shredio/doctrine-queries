<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Tests\Doctrine\NonEmptyStringType;

/**
 * Columns of a custom DBAL type whose PHPStan descriptor returns a refined string type (non-empty-string).
 */
#[Entity]
class Keyword
{

	/**
	 * @param non-empty-string $name
	 * @param non-empty-string|null $description
	 */
	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		public int $id,
		#[Column(type: NonEmptyStringType::Name, length: 50)]
		public string $name,
		#[Column(type: NonEmptyStringType::Name, length: 120, nullable: true)]
		public ?string $description = null,
	)
	{
	}

}
