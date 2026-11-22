<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * Intentionally unrelated to Role: ProjectMember::$role and Author::$role share the association name
 * but point to different entities.
 */
#[Entity]
class ProjectRole
{

	/**
	 * A to-many association whose target has a single identifier, unlike Article::$hashTags.
	 *
	 * @var Collection<int, ProjectMember>
	 */
	#[OneToMany(targetEntity: ProjectMember::class, mappedBy: 'role')]
	private Collection $members;

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		private int $id,
		#[Column(type: 'string', length: 120)]
		private string $label,
	) {
		$this->members = new ArrayCollection();
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	/**
	 * @return Collection<int, ProjectMember>
	 */
	public function getMembers(): Collection
	{
		return $this->members;
	}

}
