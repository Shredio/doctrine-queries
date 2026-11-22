<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class ProjectMember
{

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		private int $id,
		#[ManyToOne(targetEntity: Author::class)]
		#[JoinColumn(nullable: false)]
		private Author $author,
		#[ManyToOne(targetEntity: ProjectRole::class, inversedBy: 'members')]
		#[JoinColumn(nullable: false)]
		private ProjectRole $role,
	) {}

	public function getId(): int
	{
		return $this->id;
	}

	public function getAuthor(): Author
	{
		return $this->author;
	}

	public function getRole(): ProjectRole
	{
		return $this->role;
	}

}
