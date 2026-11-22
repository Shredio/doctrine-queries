<?php declare(strict_types = 1);

namespace Tests\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\Enum\Priority;

/**
 * One column of each commonly used DBAL type, for comparing the inferred types with what the database driver returns.
 */
#[Entity]
class TypedValues
{

	/**
	 * @param array<string, mixed> $payload
	 * @param list<string> $tags
	 */
	public function __construct(
		#[Id]
		#[Column(type: 'integer')]
		public int $id,
		#[Column(type: 'boolean')]
		public bool $flag,
		#[Column(type: 'smallint')]
		public int $small,
		#[Column(type: 'bigint')]
		public int|string $big,
		#[Column(type: 'decimal', precision: 10, scale: 2)]
		public string $price,
		#[Column(type: 'float')]
		public float $ratio,
		#[Column(type: 'string', length: 50)]
		public string $label,
		#[Column(type: 'text')]
		public string $note,
		#[Column(type: 'ascii_string', length: 20)]
		public string $code,
		#[Column(type: 'guid')]
		public string $uuid,
		#[Column(type: 'date_immutable')]
		public DateTimeImmutable $day,
		#[Column(type: 'time_immutable')]
		public DateTimeImmutable $time,
		#[Column(type: 'datetime_immutable')]
		public DateTimeImmutable $moment,
		#[Column(type: 'json')]
		public array $payload,
		#[Column(type: 'simple_array')]
		public array $tags,
		#[Column(type: 'string', length: 10, enumType: ArticleType::class)]
		public ArticleType $kind,
		#[Column(type: 'smallint', enumType: Priority::class)]
		public Priority $priority,
		#[Column(type: 'boolean', nullable: true)]
		public ?bool $nullableFlag = null,
		#[Column(type: 'integer', nullable: true)]
		public ?int $nullableNumber = null,
		#[Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
		public ?string $nullablePrice = null,
		#[Column(type: 'float', nullable: true)]
		public ?float $nullableRatio = null,
		#[Column(type: 'datetime_immutable', nullable: true)]
		public ?DateTimeImmutable $nullableMoment = null,
	)
	{
	}

}
