<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Tests\Doctrine\BinaryId;
use Tests\Doctrine\BinaryIdType;

/**
 * Identified by a value object of a custom DBAL type that is stored as bytes.
 */
#[Entity]
class Portfolio
{

	/** @var Collection<int, InvestmentAccount> */
	#[OneToMany(targetEntity: InvestmentAccount::class, mappedBy: 'portfolio')]
	private Collection $accounts;

	public function __construct(
		#[Id]
		#[Column(type: BinaryIdType::Name)]
		private BinaryId $id,
		#[Column(type: 'string', length: 120)]
		private string $name,
	)
	{
		$this->accounts = new ArrayCollection();
	}

	public function getId(): BinaryId
	{
		return $this->id;
	}

	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return Collection<int, InvestmentAccount>
	 */
	public function getAccounts(): Collection
	{
		return $this->accounts;
	}

}
