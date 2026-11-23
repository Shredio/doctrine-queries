<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Stringable;

/**
 * Identifier stored as bytes, like a UUID in a BINARY(16) column. It does not tell Doctrine its type and its text form
 * differs from the stored bytes, so it matches a row only when it is bound by the type of the column.
 */
final readonly class BinaryId implements Stringable
{

	public function __construct(
		public string $bytes,
	)
	{
	}

	public static function fromHex(string $hex): self
	{
		return new self((string) hex2bin($hex));
	}

	public function __toString(): string
	{
		return bin2hex($this->bytes);
	}

}
