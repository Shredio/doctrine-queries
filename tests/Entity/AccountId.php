<?php declare(strict_types = 1);

namespace Tests\Entity;

final class AccountId
{

	public function __construct(
		private int $value,
	) {}

	public function getValue(): int
	{
		return $this->value;
	}

}