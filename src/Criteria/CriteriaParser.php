<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Criteria;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Shredio\DoctrineQueries\Allocator\ParameterCounter;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Query\SubQuery;
use Shredio\DoctrineQueries\Select\Field;

/**
 * @internal
 */
final readonly class CriteriaParser
{

	/**
	 * Supported operators (normalized to upper case with single spaces) mapped to the DQL operator they produce.
	 */
	private const array SupportedOperators = [
		'=' => '=',
		'!=' => '!=',
		'<>' => '!=',
		'<' => '<',
		'<=' => '<=',
		'>' => '>',
		'>=' => '>=',
		'LIKE' => 'LIKE',
		'NOT LIKE' => 'NOT LIKE',
	];

	private function __construct()
	{
		// Prevent instantiation
	}

	/**
	 * @param array<string, mixed> $criteria Criteria of fields, the groups of Criteria::anyOf() are parsed by their alternatives
	 * @param ParameterCounter $parameterCounter Shared by the criteria of one query that are parsed separately
	 * @return iterable<ParsedCriteria>
	 */
	public static function parse(
		array $criteria,
		string $suffix = '',
		?QueryMetadata $queryMetadata = null,
		ParameterCounter $parameterCounter = new ParameterCounter(),
	): iterable
	{
		$parameterName = 'param';
		if ($suffix !== '') {
			$parameterName .= '_' . $suffix;
		}
		$parameterName .= '_';

		foreach ($criteria as $criteriaKey => $value) {
			if ($criteriaKey === '') {
				throw new InvalidArgumentException('Field cannot be empty');
			}

			[$field, $rawOperator] = self::parseSingleField($criteriaKey);
			$operator = self::normalizeOperator($rawOperator) ?? throw new InvalidArgumentException(sprintf(
				'Unsupported operator "%s" in criteria "%s". Supported operators are: %s.',
				$rawOperator,
				$criteriaKey,
				implode(', ', self::getSupportedOperators()),
			));
			$operand = '%s';
			$parameters = null;
			$orIsNull = false;

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

				// NULL equals no value in SQL, so "IN (NULL)" matches no row, it is compared by IS NULL instead.
				$containsNull = in_array(null, $value, true);
				if ($containsNull) {
					$value = array_values(array_filter($value, static fn (mixed $item): bool => $item !== null));
				}

				if ($operator === '=') {
					if ($value === [] && $containsNull) { // only NULL, an empty list matches no row as IN ()
						$operator = 'IS NULL';
						$operand = null;
					} else {
						$operator = 'IN';
						$operand = '(%s)';
						$orIsNull = $containsNull;
					}
				} else if ($operator === '!=') {
					if ($value === [] && !$containsNull) {
						// NOT IN with an empty list matches every row, but DBAL expands it to NOT IN (NULL), which matches none.
						continue;
					}

					if ($value === []) { // only NULL
						$operator = 'IS NOT NULL';
						$operand = null;
					} else {
						// NOT IN matches no NULL already, the NULL in the list is just left out.
						$operator = 'NOT IN';
						$operand = '(%s)';
					}
				} else {
					throw new InvalidArgumentException('Only = and != operators are allowed with array value');
				}
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
				$param = $parameterName . $parameterCounter->getNextIndex();
				$operand = sprintf($operand, ':' . $param);
			}

			yield new ParsedCriteria(
				$field,
				$operator,
				$operand,
				$param,
				$operand !== null ? $value : null,
				$parameters,
				$orIsNull,
			);
		}
	}

	/**
	 * Returns the normalized operator, or null when the operator is not supported.
	 */
	public static function normalizeOperator(string $operator): ?string
	{
		$normalized = strtoupper(trim((string) preg_replace('/\s+/', ' ', $operator)));

		return self::SupportedOperators[$normalized] ?? null;
	}

	/**
	 * @return list<string>
	 */
	public static function getSupportedOperators(): array
	{
		return array_keys(self::SupportedOperators);
	}

	/**
	 * Splits a criteria key into the field and the raw (not validated) operator.
	 *
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
