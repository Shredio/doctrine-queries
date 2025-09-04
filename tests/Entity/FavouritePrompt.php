<?php declare(strict_types = 1);

namespace Tests\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table(name: 'favourite_prompts')]
class FavouritePrompt
{

	#[Id]
	#[Column(name: 'account_id', type: 'integer')]
	private int $account;

	#[Id]
	#[ManyToOne(targetEntity: Prompt::class)]
	#[JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private Prompt $prompt;

	public function __construct(
		int $account,
		Prompt $prompt,
	) {
		$this->account = $account;
		$this->prompt = $prompt;
	}

}
