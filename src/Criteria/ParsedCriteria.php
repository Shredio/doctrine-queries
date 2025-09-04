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
	 */
	public function __construct(
		public Field $field,
		public string $operator,
		public ?string $operand,
		public ?string $parameterName,
		public mixed $value,
		public ?ArrayCollection $parameters,
	)
	{
	}

	public function getExpression(QueryMetadata $queryMetadata): string
	{
		if ($this->operand !== null) {
			return sprintf('%s %s %s', $queryMetadata->getPathForField($this->field), $this->operator, $this->operand);
		}

		return sprintf('%s %s', $queryMetadata->getPathForField($this->field), $this->operator);
	}

}
