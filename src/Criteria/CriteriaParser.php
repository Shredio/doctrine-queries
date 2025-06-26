<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Criteria;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * @internal
 */
final readonly class CriteriaParser
{

	private function __construct()
	{
		// Prevent instantiation
	}

	/**
	 * @param array<string, mixed> $criteria
	 * @return iterable<ParsedCriteria>
	 */
	public static function parse(array $criteria, string $parameterName = 'param'): iterable
	{
		$index = 0;

		foreach ($criteria as $field => $value) {
			if ($field === '') {
				throw new InvalidArgumentException('Field cannot be empty');
			}

			[$field, $operator] = self::parseOperator($field);
			$operand = '%s';
			$parameters = null;

			if ($value instanceof QueryBuilder || $value instanceof Query) {
				$dql = $value->getDQL();

				if ($operator === '=') {
					$operator = sprintf('IN(%s)', $dql);
				} else if ($operator === '!=') {
					$operator = sprintf('NOT IN(%s)', $dql);
				} else {
					throw new InvalidArgumentException('Only = and != operators are allowed with QueryBuilder or Query value');
				}

				$parameters = $value->getParameters();
				$operand = null;
			} else if (is_iterable($value)) {
				$value = self::toArray($value);

				if ($operator === '=') {
					$operator = 'IN';
				} else if ($operator === '!=') {
					$operator = 'NOT IN';
				} else {
					throw new InvalidArgumentException('Only = and != operators are allowed with array value');
				}

				$operand = '(%s)';
			} else if ($value === null) {
				if ($operator === '=') {
					$operator = 'IS NULL';
					$operand = null;
				} else if ($operator === '!=') {
					$operator = 'IS NOT NULL';
					$operand = null;
				} else {
					throw new InvalidArgumentException('Only = and != operators are allowed with NULL value');
				}
			}

			$param = null;

			if ($operand !== null) {
				$index++;

				$param = $parameterName . $index;
				$operand = sprintf($operand, ':' . $param);
			}

			yield new ParsedCriteria(
				$field,
				$operator,
				$operand,
				$param,
				$operand !== null ? $value : null,
				$parameters,
			);
		}
	}

	/**
	 * @return array{string, string}
	 */
	private static function parseOperator(string $field): array
	{
		if (($pos = strpos($field, ' ')) !== false) {
			return [substr($field, 0, $pos), substr($field, $pos + 1)];
		}

		return [$field, '='];
	}

	/**
	 * @param mixed[] $value
	 * @return mixed[]
	 */
	private static function toArray(iterable $value): array
	{
		return is_array($value) ? $value : iterator_to_array($value, false);
	}

}
