<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;

/**
 * The owning side of a many-to-many association, the other to-many associations are one-to-many.
 */
#[Entity]
class Note
{

	/** @var Collection<int, NoteTag> */
	#[ManyToMany(targetEntity: NoteTag::class, inversedBy: 'notes')]
	private Collection $tags;

	/**
	 * @param list<NoteTag> $tags
	 */
	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		private int $id,
		#[Column(type: 'string', length: 120)]
		private string $title,
		array $tags = [],
	) {
		$this->tags = new ArrayCollection($tags);
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	/**
	 * @return Collection<int, NoteTag>
	 */
	public function getTags(): Collection
	{
		return $this->tags;
	}

}
