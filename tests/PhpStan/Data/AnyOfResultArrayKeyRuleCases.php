<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Criteria\Criteria;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;

class AnyOfResultArrayKeyRuleCases
{

	public ScalarQueries $scalarQueries;

	/**
	 * A row can match by another alternative, its key can still be NULL.
	 */
	public function keyExcludedOnlyByAnAlternative(): void
	{
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', [Criteria::anyOf(['symbol !=' => null], ['id' => 1])])->asArray();
		$this->scalarQueries->findIndexedBy(Article::class, 'symbol', [Criteria::anyOf(['symbol !=' => null], ['symbol !=' => null, 'id' => 1])], select: ['id'])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', [Criteria::anyOf(['symbol !=' => null])])->asArray();
	}

	public function keyExcludedByTheCriteriaAroundTheGroup(): void
	{
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['symbol !=' => null, Criteria::anyOf(['symbol' => null], ['id' => 1])])->asArray();
		$this->scalarQueries->findIndexedBy(Article::class, 'symbol', [Criteria::anyOf(['id' => 2], ['id' => 1]), 'symbol !=' => null], select: ['id'])->asArray();
	}

}
