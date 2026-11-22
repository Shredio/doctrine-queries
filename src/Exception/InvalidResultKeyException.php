<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use LogicException;

/**
 * A row cannot be stored under its key, PHP arrays accept only integers and strings as keys.
 */
final class InvalidResultKeyException extends LogicException
{

	public function __construct(
		public readonly string $fieldName,
		mixed $key,
	)
	{
		$tip = match (true) {
			$key === null => sprintf('Exclude the rows with the criteria [\'%s !=\' => null], or iterate over yield().', $this->fieldName),
			is_object($key) => 'Use ScalarQueries to get the database value, or iterate over yield().',
			default => 'Iterate over yield() instead.',
		};

		parent::__construct(sprintf(
			'The key "%s" must be a string or an integer to be used as an array key, got %s. %s',
			$this->fieldName,
			get_debug_type($key),
			$tip,
		));
	}

}
