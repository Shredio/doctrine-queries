<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Field;

final class DoctrineAliasAllocator
{

	private string $currentAlias = 'a';

	public function allocate(): string
	{
		$alias = $this->currentAlias;
		$this->currentAlias++;
		return $alias;
	}

}