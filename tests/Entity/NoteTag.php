<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;

/**
 * The inverse side of the many-to-many association Note::$tags.
 */
#[Entity]
class NoteTag
{

	/** @var Collection<int, Note> */
	#[ManyToMany(targetEntity: Note::class, mappedBy: 'tags')]
	private Collection $notes;

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		private int $id,
		#[Column(type: 'string', length: 120)]
		private string $name,
	) {
		$this->notes = new ArrayCollection();
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return Collection<int, Note>
	 */
	public function getNotes(): Collection
	{
		return $this->notes;
	}

}
