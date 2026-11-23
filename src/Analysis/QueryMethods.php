<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Aggregation\AggregateFunction;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * The analysed methods of the queries.
 */
final readonly class QueryMethods
{

	public const int EntityPosition = 0;

	public const string EntityName = 'entity';

	/**
	 * The analysed parameters of each method as [position => parameter name], the entity at position 0 is not listed.
	 * The names have to match the method signatures, named arguments are looked up by them.
	 * Positions that are not listed (e.g. distinct) are not analysed.
	 */
	public const array Parameters = [
		ScalarQueries::class => self::FieldQueriesParameters,
		ArrayQueries::class => self::FieldQueriesParameters,
		ObjectQueries::class => [
			'findBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'preload', 4 => 'pagination', 5 => 'joinConfig'],
			'findOneBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'joinConfig'],
		],
		DoctrineQueries::class => [
			'countBy' => [1 => 'criteria', 2 => 'field', 4 => 'joinConfig'],
			'countGroupedBy' => [1 => 'groupBy', 2 => 'criteria', 3 => 'field', 5 => 'joinConfig'],
			'sumBy' => self::AggregationParameters,
			'minBy' => self::AggregationParameters,
			'maxBy' => self::AggregationParameters,
			'sumGroupedBy' => self::GroupedAggregationParameters,
			'minGroupedBy' => self::GroupedAggregationParameters,
			'maxGroupedBy' => self::GroupedAggregationParameters,
			'existsBy' => [1 => 'criteria'],
			'existsManyBy' => [1 => 'values'],
			'deleteBy' => [1 => 'criteria'],
			'subQuery' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'joinConfig'],
		],
	];

	/**
	 * The classes whose results have a type inferred from the selected fields.
	 */
	public const array QueryTypes = [
		ScalarQueries::class => QueryType::Scalar,
		ArrayQueries::class => QueryType::Array,
	];

	/**
	 * The aggregate function of each method of DoctrineQueries aggregating the values of a field.
	 */
	private const array AggregateFunctions = [
		'sumBy' => AggregateFunction::Sum,
		'minBy' => AggregateFunction::Min,
		'maxBy' => AggregateFunction::Max,
		'sumGroupedBy' => AggregateFunction::Sum,
		'minGroupedBy' => AggregateFunction::Min,
		'maxGroupedBy' => AggregateFunction::Max,
	];

	/**
	 * The methods of DoctrineQueries returning an aggregation for each database value of the grouped field.
	 */
	private const array GroupedMethods = [
		'countGroupedBy' => true,
		'sumGroupedBy' => true,
		'minGroupedBy' => true,
		'maxGroupedBy' => true,
	];

	private const array AggregationParameters = [1 => 'field', 2 => 'criteria', 3 => 'joinConfig'];

	private const array GroupedAggregationParameters = [1 => 'field', 2 => 'groupBy', 3 => 'criteria', 4 => 'joinConfig'];

	private const array FieldQueriesParameters = [
		'findBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'pagination', 5 => 'joinConfig'],
		'findOneBy' => [1 => 'criteria', 2 => 'orderBy', 3 => 'select', 4 => 'joinConfig'],
		'findIndexedBy' => [1 => 'indexField', 2 => 'criteria', 3 => 'orderBy', 4 => 'select', 5 => 'joinConfig'],
		'findPairsBy' => [1 => 'key', 2 => 'value', 3 => 'criteria', 4 => 'orderBy', 5 => 'joinConfig'],
		'findColumnValuesBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy', 5 => 'pagination', 6 => 'joinConfig'],
		'findSingleColumnValueBy' => [1 => 'field', 2 => 'criteria', 3 => 'orderBy', 4 => 'joinConfig'],
	];

	/**
	 * @return array<int, string>|null
	 */
	public static function getParameters(string $className, string $methodName): ?array
	{
		return self::Parameters[$className][$methodName] ?? null;
	}

	/**
	 * Whether the shared analysis infers the return type of the method.
	 */
	public static function hasReturnType(string $className, string $methodName): bool
	{
		if ($className === DoctrineQueries::class) {
			return $methodName === 'existsManyBy' || isset(self::GroupedMethods[$methodName]) || isset(self::AggregateFunctions[$methodName]);
		}

		return isset(self::QueryTypes[$className], self::Parameters[$className][$methodName]);
	}

	/**
	 * The function a method of DoctrineQueries aggregates the values of its field with, null for other methods.
	 */
	public static function getAggregateFunction(string $className, string $methodName): ?AggregateFunction
	{
		return $className === DoctrineQueries::class ? self::AggregateFunctions[$methodName] ?? null : null;
	}

	/**
	 * The kind of the values the method returns for the fields, null when it returns no field.
	 */
	public static function getQueryType(string $className, string $methodName): ?QueryType
	{
		if ($className === DoctrineQueries::class) {
			// the keys of the groups are database values
			return isset(self::GroupedMethods[$methodName]) || isset(self::AggregateFunctions[$methodName]) ? QueryType::Scalar : null;
		}

		return isset(self::Parameters[$className][$methodName]) ? self::QueryTypes[$className] ?? null : null;
	}

}
