<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Tests\Doctrine\AccountNumber;
use Tests\Doctrine\AccountNumberType;

/**
 * References an entity with a binary identifier, so its join column is binary too. Its number is a value object
 * of a custom DBAL type that is not binary.
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
		#[Column(type: AccountNumberType::Name)]
		private AccountNumber $number,
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

	public function getNumber(): AccountNumber
	{
		return $this->number;
	}

}
