<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Criteria;

use Shredio\DoctrineQueries\Field\FieldPath;
use Shredio\DoctrineQueries\Field\MappedFieldPath;

final readonly class ParsedCriteria
{

	public function __construct(
		public FieldPath $field,
		public string $operator,
		public ?string $operand,
		public ?string $parameterName,
		public mixed $value,
	)
	{
	}

	public function getExpression(MappedFieldPath $mappedField): string
	{
		if ($this->operand !== null) {
			return sprintf('%s %s %s', $mappedField->path, $this->operator, $this->operand);
		}

		return sprintf('%s %s', $mappedField->path, $this->operator);
	}

}
