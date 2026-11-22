<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\AbstractQuery;
use Shredio\DoctrineQueries\Select\QueryType;

/**
 * Query executor for returning results as associative arrays.
 * 
 * Provides database query functionality that returns results as associative arrays
 * using Doctrine's array hydration mode. This is useful when you don't need
 * full entity objects and want faster hydration performance.
 *
 * Criteria examples:
 *    - ['id' => 1] - equals
 *    - ['name !=' => 'John'] - not equals
 *    - ['age >' => 18] - greater than
 *    - ['age >=' => 18] - greater than or equal
 *    - ['name LIKE' => '%john%'] - pattern matching
 *    - ['name NOT LIKE' => '%admin%'] - negative pattern matching
 *    - ['status' => null] - IS NULL
 *    - ['status !=' => null] - IS NOT NULL
 *    - ['id' => [1, 2, 3]] - IN clause
 *    - ['id !=' => [1, 2, 3]] - NOT IN clause
 *    - ['id >' => 1, 'status' => 'active'] - multiple criteria (AND)
 *    - etc.
 *
 * Sorting examples:
 *    - ['name' => SortDirection::Ascending] - sort by name ascending
 *    - ['createdAt' => SortDirection::Descending] - sort by creation date descending
 *    - ['position' => new NullsLast(SortDirection::Ascending)] - sort by position ascending, NULL values last
 *
 * Select examples:
 *   - ['id', 'name'] - select only id and name fields
 *   - ['name' => 'personName'] - select the name field and alias it as personName
 *   - ['*'] - select all entity fields (without relations)
 *   - ['**'] - select all entity fields including relations (relation identifiers only)
 *   - ['author.name'] - select related field from author relation
 *   - ['author.*'] - select all fields from author relation
 *   - ['author.**'] - select all fields from author relation including its relations
 *   - ['*', 'author.**' => 'author_'] - select all entity fields and all author fields with prefix
 *
 * Join configuration examples:
 *   - 'left' - use LEFT JOIN for all relations (default, includes null values)
 *   - 'inner' - use INNER JOIN for all relations (excludes null values)
 *   - ['author' => 'inner'] - use INNER JOIN for author relation, LEFT for others
 *   - ['author.role' => 'left'] - use LEFT JOIN for author.role relation
 *
 * @extends FieldQueries<mixed>
 */
final readonly class ArrayQueries extends FieldQueries
{

	protected function getQueryType(): QueryType
	{
		return QueryType::Array;
	}

	protected function getHydrationMode(): int
	{
		return AbstractQuery::HYDRATE_ARRAY;
	}

}
