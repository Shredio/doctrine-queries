<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToOne;

#[Entity]
class OneToOnePrimary
{

	public function __construct(
		#[Id]
		#[OneToOne(targetEntity: OneToOneAssoc::class)]
		#[JoinColumn(nullable: false, onDelete: 'CASCADE')]
		public OneToOneAssoc $assoc,
		#[Column(type: 'string')]
		public string $normalField,
	)
	{
	}

}
