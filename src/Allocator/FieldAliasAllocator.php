<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Allocator;

final class FieldAliasAllocator
{

	/** @var array<string, non-empty-string> */
	private array $aliases = [];

	public function __construct(
		private readonly AliasCounter $aliasCounter = new AliasCounter(),
	)
	{
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public function getFor(string $path): string
	{
		if (isset($this->aliases[$path])) {
			return $this->aliases[$path];
		}

		$pos = strrpos($path, '.');
		if ($pos !== false) {
			$this->getFor(substr($path, 0, $pos));
		}

		return $this->aliases[$path] = $this->getNextAlias();
	}

	/**
	 * @return array<string, non-empty-string> List of joins as [path => alias]
	 */
	public function getAliases(): array
	{
		return $this->aliases;
	}

	/**
	 * @return non-empty-string
	 */
	public function getNextAlias(): string
	{
		return $this->aliasCounter->getNextAlias();
	}

	public function createChild(): FieldAliasAllocator
	{
		return new self($this->aliasCounter);
	}

}
