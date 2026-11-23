<?php declare(strict_types = 1);

namespace Tests\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Tests\Doctrine\AccountNumber;
use Tests\Doctrine\AccountNumberType;
use Tests\Doctrine\FloatDecimalType;
use Tests\Entity\Enum\Priority;

/**
 * Integer columns mapped as unsigned next to a signed one, and columns of custom DBAL types: a type whose fields can
 * be summed (FloatDecimalType) and a type stored as a number whose fields cannot (AccountNumberType).
 */
#[Entity]
class UsageRecord
{

	public function __construct(
		#[Id]
		#[Column(type: 'integer', options: ['unsigned' => true])]
		public int $id,
		#[Column(type: 'string', length: 20)]
		public string $provider,
		#[Column(type: 'integer', options: ['unsigned' => true])]
		public int $tokens,
		#[Column(type: 'integer')]
		public int $balance,
		#[Column(type: FloatDecimalType::Name, precision: 12, scale: 6)]
		public float $cost,
		#[Column(type: AccountNumberType::Name)]
		public AccountNumber $account,
		#[Column(type: 'smallint', enumType: Priority::class, options: ['unsigned' => true])]
		public Priority $priority = Priority::Low,
		#[Column(type: 'bigint', options: ['unsigned' => true])]
		public int|string $bytes = 0,
		#[Column(type: 'decimal', precision: 10, scale: 2, options: ['unsigned' => true])]
		public string $fee = '0.00',
		#[Column(type: 'smallint', nullable: true, options: ['unsigned' => true])]
		public ?int $retries = null,
		#[Column(type: FloatDecimalType::Name, precision: 12, scale: 6, nullable: true)]
		public ?float $discount = null,
		#[ManyToOne(targetEntity: self::class)]
		#[JoinColumn(nullable: true)]
		public ?UsageRecord $previous = null,
	)
	{
	}

}
