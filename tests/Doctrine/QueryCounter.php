<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Psr\Log\AbstractLogger;

/**
 * Collects the SQL queries logged by the DBAL logging middleware.
 *
 * Extends AbstractLogger and leaves log() without parameter types, so it is compatible with psr/log 1, 2 and 3.
 */
final class QueryCounter extends AbstractLogger
{

	private int $index = 0;

	/** @var array<int, list<string>> */
	private array $memory = [];

	/**
	 * @return callable(): list<string>
	 */
	public function capture(): callable
	{
		$index = $this->index;
		$this->memory[$index] = [];
		$this->index++;

		return function () use ($index): array {
			if (!isset($this->memory[$index])) {
				throw new \RuntimeException('Queries have already been retrieved for this capture.');
			}

			$queries = $this->memory[$index];
			unset($this->memory[$index]);

			return $queries;
		};
	}

	/**
	 * @param mixed $level
	 * @param string|\Stringable $message
	 * @param array<mixed> $context
	 */
	public function log($level, $message, array $context = []): void
	{
		if (isset($context['sql']) && is_string($context['sql'])) {
			foreach ($this->memory as &$queries) {
				$queries[] = $context['sql'];
			}
		}
	}

}
