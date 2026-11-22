<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/**
 * An entity identified by a string, e.g. for getLastId() which supports only integer identifiers.
 */
#[Entity]
class Country
{

	public function __construct(
		#[Id]
		#[Column(type: 'string', length: 2)]
		private string $code,
		#[Column(type: 'string', length: 120)]
		private string $name,
	) {}

	public function getCode(): string
	{
		return $this->code;
	}

	public function getName(): string
	{
		return $this->name;
	}

}
