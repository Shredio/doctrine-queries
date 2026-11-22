<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Result;

use Doctrine\ORM\Query;

/**
 * @internal
 */
final readonly class QueryResultIterator
{

	/**
	 * Marks a query joining a to-many association. Doctrine refuses to iterate such a query (to protect the hydration of
	 * fetch-joined collections), even when the association is joined only to select or order by its fields.
	 */
	public const string MaterializeHint = 'shredio.doctrineQueries.materialize';

	private function __construct()
	{
		// Prevent instantiation
	}

	/**
	 * @template TValue
	 * @param Query<int, TValue> $query
	 * @return iterable<int, TValue>
	 */
	public static function iterate(Query $query): iterable
	{
		if ($query->getHint(self::MaterializeHint) === true) {
			/** @var list<TValue> */
			return $query->execute();
		}

		/** @var iterable<int, TValue> */
		return $query->toIterable(hydrationMode: $query->getHydrationMode());
	}

}
