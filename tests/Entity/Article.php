<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Symfony\Component\Clock\DatePoint;
use Tests\Doctrine\Symbol;

#[Entity]
class Article
{

	#[OneToMany(targetEntity: ArticleHashtag::class, mappedBy: 'article')]
	private Collection $hashTags;

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		private int $id,
		#[Column(type: 'string')]
		private string $title,
		#[Column(type: 'text')]
		private string $content,
		#[ManyToOne(targetEntity: Author::class)]
		private Author $author,
		#[Column(type: 'symbol', nullable: true)]
		private ?Symbol $symbol = null,
		#[Column(type: 'datetime_immutable')]
		private \DateTimeImmutable $createdAt = new DatePoint(),
	) {
		$this->hashTags = new ArrayCollection();
	}

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

	public function getAuthor(): Author
	{
		return $this->author;
	}

	public function getSymbol(): ?Symbol
	{
		return $this->symbol;
	}

	public function getCreatedAt(): \DateTimeImmutable
	{
		return $this->createdAt;
	}

}
