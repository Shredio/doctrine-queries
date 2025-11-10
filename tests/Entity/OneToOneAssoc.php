<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

#[Entity]
class OneToOneAssoc
{

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		#[GeneratedValue]
		public int $id,
		#[Column(type: 'string')]
		public string $normalField,
		#[Column(type: 'string', nullable: true)]
		public ?string $nullableField = null,
	)
	{
	}

}
