<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Field;

use InvalidArgumentException;

/**
 * Represents a field path for navigating entity relationships and fields.
 * 
 * Supports dot-notation paths like 'account', 'account.id', or 'account.role.name'
 * for traversing entity associations and accessing fields.
 */
final readonly class FieldPath
{

	/** @var non-empty-list<string> */
	private array $segments;

	/**
	 * @param non-empty-list<string> $segments Path segments (e.g., ['account', 'role', 'name'])
	 */
	public function __construct(array $segments)
	{
		$this->segments = $segments;
	}

	/**
	 * Creates a FieldPath from a dot-notation string.
	 * 
	 * @param string $path Dot-notation path (e.g., 'account.role.name')
	 * @throws InvalidArgumentException When path is empty
	 */
	public static function createFromString(string $path): self
	{
		if ($path === '') {
			throw new InvalidArgumentException('Path cannot be empty');
		}

		return new self(explode('.', $path));
	}

	/**
	 * Returns the root segment of the path.
	 * 
	 * @return string Root segment (e.g., 'account' for 'account.role.name')
	 */
	public function getRoot(): string
	{
		return $this->segments[0];
	}

	/**
	 * Returns the parent path as a string, or null for single-segment paths.
	 * 
	 * @return string|null Parent path (e.g., 'account.role' for 'account.role.name', null for 'account')
	 */
	public function getParent(): ?string
	{
		if (count($this->segments) <= 1) {
			return null;
		}

		$parentSegments = array_slice($this->segments, 0, -1);
		return implode('.', $parentSegments);
	}

	/**
	 * Returns a new FieldPath with the remaining segments after the root, or null for single-segment paths.
	 * 
	 * @return self|null Next FieldPath (e.g., FieldPath('role.name') for 'account.role.name', null for 'account')
	 */
	public function next(): ?self
	{
		if (count($this->segments) <= 1) {
			return null;
		}

		/** @var non-empty-list<string> $nextSegments */
		$nextSegments = array_slice($this->segments, 1);
		return new self($nextSegments);
	}

	/**
	 * Returns the full path as a dot-notation string.
	 * 
	 * @return string Full path (e.g., 'account.role.name')
	 */
	public function getPath(): string
	{
		return implode('.', $this->segments);
	}

}
