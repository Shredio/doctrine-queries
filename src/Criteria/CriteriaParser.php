<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Criteria;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Query\SubQuery;
use Shredio\DoctrineQueries\Select\Field;

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
	public static function parse(
		array $criteria,
		string $suffix = '',
		?QueryMetadata $queryMetadata = null,
	): iterable
	{
		$index = 0;

		$parameterName = 'param';
		if ($suffix !== '') {
			$parameterName .= '_' . $suffix;
		}
		$parameterName .= '_';

		foreach ($criteria as $field => $value) {
			if ($field === '') {
				throw new InvalidArgumentException('Field cannot be empty');
			}

			[$field, $operator] = self::parseSingleField($field);
			$operand = '%s';
			$parameters = null;

			if ($value instanceof SubQuery) {
				if ($queryMetadata === null) {
					throw new InvalidArgumentException('QueryMetadata is required when using SubQuery');
				}

				$value = $value->build($queryMetadata);
			}

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
	 * @return array{Field, string}
	 */
	public static function parseSingleField(string $field): array
	{
		if (($pos = strpos($field, ' ')) !== false) {
			return [new Field(substr($field, 0, $pos)), substr($field, $pos + 1)];
		}

		return [new Field($field), '='];
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
