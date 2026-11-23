<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

/**
 * References an entity with a binary identifier, so its join column is binary too.
 */
#[Entity]
class InvestmentAccount
{

	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		private int $id,
		#[ManyToOne(targetEntity: Portfolio::class, inversedBy: 'accounts')]
		#[JoinColumn(nullable: false)]
		private Portfolio $portfolio,
	)
	{
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getPortfolio(): Portfolio
	{
		return $this->portfolio;
	}

}
