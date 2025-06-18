<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class ArticleHashtag
{

	public function __construct(
		#[Id]
		#[ManyToOne(targetEntity: Article::class, inversedBy: 'hashtags')]
		private Article $article,
		#[Column(type: 'string')]
		private string $hashtag,
	) {}

	public function getArticle(): Article
	{
		return $this->article;
	}

	public function getHashtag(): string
	{
		return $this->hashtag;
	}

}
