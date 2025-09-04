<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Pagination;

final readonly class Pagination
{

	/**
	 * @param int<1, max>|null $limit  Maximum number of records to return (for no limit, use null)
	 * @param int<0, max>|null $offset Number of records to skip (for no offset, use null)
	 */
	public function __construct(
		public ?int $limit = null,
		public ?int $offset = null,
	)
	{
	}

}
