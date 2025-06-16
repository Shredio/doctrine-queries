<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

#[Entity]
class Author
{

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		private int $id,
		#[Column(type: 'string', length: 120)]
		private string $name,
	) {}

	public function getId(): int
	{
		return $this->id;
	}

	public function getName(): string
	{
		return $this->name;
	}

}
