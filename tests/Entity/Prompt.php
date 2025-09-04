<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

#[Entity]
class Prompt
{

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		private int $id,
		#[Column(type: 'string', length: 255)]
		private string $title,
		#[Column(type: 'text')]
		private string $content,
	) {}

	public function getId(): int
	{
		return $this->id;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getContent(): string
	{
		return $this->content;
	}

}