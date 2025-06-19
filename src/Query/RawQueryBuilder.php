<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Shredio\DoctrineQueries\Result\DatabaseRawResults;

final class RawQueryBuilder
{

	/** @var array<string, mixed> */
	private array $parameters = [];

	/** @var array<string, string|Type|ParameterType|ArrayParameterType> */
	private array $types = [];

	public function __construct(
		private string $query,
		private Connection $connection,
	)
	{
	}

	public function setParameter(string $key, mixed $value, string|Type|ParameterType|ArrayParameterType|null $type = null): self
	{
		$this->parameters[$key] = $value;
		$this->types[$key] = $type ?? $this->detectType($value);

		return $this;
	}

	public function getResult(): DatabaseRawResults
	{
		return new DatabaseRawResults($this->connection->executeQuery($this->query, $this->parameters, $this->types));
	}

	private function detectType(mixed $value): string|ArrayParameterType|ParameterType
	{
		if (is_int($value)) {
			return Types::INTEGER;
		}

		if (is_bool($value)) {
			return Types::BOOLEAN;
		}

		if ($value instanceof DateTimeImmutable) {
			return Types::DATETIME_IMMUTABLE;
		}

		if ($value instanceof DateTimeInterface) {
			return Types::DATETIME_MUTABLE;
		}

		if ($value instanceof DateInterval) {
			return Types::DATEINTERVAL;
		}

		if ($value instanceof BackedEnum) {
			return is_int($value->value)
				? Types::INTEGER
				: Types::STRING;
		}

		if (is_array($value)) {
			$firstValue = current($value);
			if ($firstValue instanceof BackedEnum) {
				$firstValue = $firstValue->value;
			}

			return is_int($firstValue)
				? ArrayParameterType::INTEGER
				: ArrayParameterType::STRING;
		}

		return ParameterType::STRING;
	}

}
