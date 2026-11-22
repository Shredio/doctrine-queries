<?php declare(strict_types = 1);

namespace Tests\Unit;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Tests\Doctrine\Symbol;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\Enum\Priority;
use Tests\Entity\TypedValues;

trait Helpers
{

	/**
	 * Replaces hydrated objects with descriptive strings, so whole rows can be compared with assertSame().
	 */
	private static function describeObjects(mixed $value): mixed
	{
		if (is_array($value)) {
			return array_map(self::describeObjects(...), $value);
		}

		return match (true) {
			$value instanceof DateTimeInterface => sprintf('%s(%s)', $value::class, $value->format('Y-m-d H:i:s')),
			$value instanceof BackedEnum => sprintf('%s::%s', $value::class, $value->name),
			$value instanceof Symbol => sprintf('Symbol(%s)', $value->value),
			is_object($value) => throw new \LogicException(sprintf('Unexpected object %s.', $value::class)),
			default => $value,
		};
	}

	private static function createTypedValues(int $id, string $price = '12.50', string $day = '2021-01-02'): TypedValues
	{
		return new TypedValues(
			id: $id,
			flag: true,
			small: 7,
			big: 1,
			price: $price,
			ratio: 0.5,
			label: 'label',
			note: 'note',
			code: 'ABC',
			uuid: '0b6f6b2e-4a6b-4bfa-9d39-7a4b9b9c1d2e',
			day: new DateTimeImmutable($day),
			time: new DateTimeImmutable('03:04:05'),
			moment: new DateTimeImmutable(sprintf('%s 03:04:05', $day)),
			payload: ['key' => 1],
			tags: ['first'],
			kind: ArticleType::News,
			priority: Priority::High,
		);
	}

	/**
	 * @param iterable<mixed> $rows
	 * @return list<mixed>
	 */
	private static function column(iterable $rows, string $column): array
	{
		$values = [];
		foreach ($rows as $row) {
			self::assertIsArray($row);
			self::assertArrayHasKey($column, $row);
			$values[] = $row[$column];
		}

		return $values;
	}

}
