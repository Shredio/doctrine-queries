<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

/**
 * References an entity identified by an association, so its join column references no field of that entity.
 */
#[Entity]
class OneToOnePrimaryReference
{

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		public int $id,
		#[ManyToOne(targetEntity: OneToOnePrimary::class)]
		#[JoinColumn(name: 'primary_id', referencedColumnName: 'assoc_id', nullable: false)]
		public OneToOnePrimary $primary,
	)
	{
	}

}
