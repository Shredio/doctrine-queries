<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

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
			return $methodName === 'existsManyBy';
		}

		return isset(self::QueryTypes[$className], self::Parameters[$className][$methodName]);
	}

}
