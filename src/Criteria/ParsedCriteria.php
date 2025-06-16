<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Criteria;

final readonly class ParsedCriteria
{

	public function __construct(
		public string $field,
		public string $operator,
		public ?string $operand,
		public ?string $parameterName,
		public mixed $value,
	)
	{
	}

	public function getExpression(): string
	{
		if ($this->operand !== null) {
			return sprintf('%s %s %s', $this->field, $this->operator, $this->operand);
		}

		return sprintf('%s %s', $this->field, $this->operator);
	}

}
