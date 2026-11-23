<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToOne;

/**
 * Associations by a composite foreign key, FavouritePrompt is identified by the account and the prompt.
 */
#[Entity]
class FavouritePromptReference
{

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		public int $id,
		#[ManyToOne(targetEntity: FavouritePrompt::class)]
		#[JoinColumn(name: 'favourite_account_id', referencedColumnName: 'account_id', nullable: false)]
		#[JoinColumn(name: 'favourite_prompt_id', referencedColumnName: 'prompt_id', nullable: false)]
		public FavouritePrompt $favourite,
		#[OneToOne(targetEntity: FavouritePrompt::class)]
		#[JoinColumn(name: 'pinned_account_id', referencedColumnName: 'account_id')]
		#[JoinColumn(name: 'pinned_prompt_id', referencedColumnName: 'prompt_id')]
		public ?FavouritePrompt $pinnedFavourite = null,
	)
	{
	}

}
