<?php declare(strict_types = 1);

namespace Tests\Doctrine;

/**
 * Value object of a custom DBAL type stored as an integer. It does not tell Doctrine its type and has no text form,
 * so it can be bound only by the type of the column.
 */
final readonly class AccountNumber
{

	public function __construct(
		public int $value,
	)
	{
	}

}
