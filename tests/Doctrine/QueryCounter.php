<?php declare(strict_types = 1);

namespace Tests\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\InternalProxy;
use Doctrine\Persistence\AbstractManagerRegistry;
use Psr\Log\LoggerInterface;
use Stringable;

final class QueryCounter implements LoggerInterface
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

	public function log($level, Stringable|string $message, array $context = []): void
	{
		if (isset($context['sql']) && is_string($context['sql'])) {
			foreach ($this->memory as &$queries) {
				$queries[] = $context['sql'];
			}
		}
	}

	public function emergency(Stringable|string $message, array $context = []): void
	{
		$this->log(__FUNCTION__, $message, $context);
	}

	public function alert(Stringable|string $message, array $context = []): void
	{
		$this->log(__FUNCTION__, $message, $context);

	}

	public function critical(Stringable|string $message, array $context = []): void
	{
		$this->log(__FUNCTION__, $message, $context);
	}

	public function error(Stringable|string $message, array $context = []): void
	{
		$this->log(__FUNCTION__, $message, $context);
	}

	public function warning(Stringable|string $message, array $context = []): void
	{
		$this->log(__FUNCTION__, $message, $context);
	}

	public function notice(Stringable|string $message, array $context = []): void
	{
		$this->log(__FUNCTION__, $message, $context);
	}

	public function info(Stringable|string $message, array $context = []): void
	{
		$this->log(__FUNCTION__, $message, $context);
	}

	public function debug(Stringable|string $message, array $context = []): void
	{
		$this->log(__FUNCTION__, $message, $context);
	}

}
