<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Criteria;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Select\Field;

final readonly class ParsedCriteria
{

	/**
	 * @param ArrayCollection<int, Parameter> $parameters
	 * @param bool $orIsNull Whether NULL matches too, for a list of values containing NULL
	 */
	public function __construct(
		public Field $field,
		public string $operator,
		public ?string $operand,
		public ?string $parameterName,
		public mixed $value,
		public ?ArrayCollection $parameters,
		public bool $orIsNull = false,
	)
	{
	}

	public function getExpression(QueryMetadata $queryMetadata): string
	{
		$path = $queryMetadata->getPathForField($this->field);

		if ($this->operand === null) {
			return sprintf('%s %s', $path, $this->operator);
		}

		$expression = sprintf('%s %s %s', $path, $this->operator, $this->operand);

		return $this->orIsNull ? sprintf('(%s OR %s IS NULL)', $expression, $path) : $expression;
	}

}
