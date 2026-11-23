<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;

class CriteriaNarrowingResultArrayKeyRuleCases
{

	public ScalarQueries $scalarQueries;

	/**
	 * The query can run without the criteria, or with a value that matches the rows with NULL.
	 *
	 * @param array{'symbol !='?: null} $optionalCriteria
	 * @param list<string> $symbols
	 */
	public function keyNotExcludedInEveryCase(bool $condition, array $optionalCriteria, mixed $mixed, array $symbols): void
	{
		$criteria = $condition ? ['symbol !=' => null] : [];

		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', $criteria)->asArray();
		$this->scalarQueries->findIndexedBy(Article::class, 'symbol', $optionalCriteria, select: ['id'])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', $condition ? ['symbol !=' => null] : ['id' => 1])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['symbol' => $mixed])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['symbol !=' => $symbols])->asArray();
	}

	/**
	 * @param list<string> $symbols
	 */
	public function keyExcludedInEveryCase(bool $condition, string $symbol, array $symbols): void
	{
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', $condition ? ['symbol !=' => null] : ['symbol' => 'a'])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['symbol !=' => $symbol])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['symbol !=' => [$symbol]])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['symbol' => $symbols])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, 'symbol', 'id', ['symbol LIKE' => $symbol])->asArray();
	}

	/**
	 * Each of the fields the key can be is checked.
	 */
	public function oneOfKeys(bool $condition): void
	{
		$this->scalarQueries->findPairsBy(Article::class, $condition ? 'id' : 'symbol', 'title')->asArray();
		$this->scalarQueries->findIndexedBy(Article::class, $condition ? 'id' : 'symbol', ['id >' => 1], select: ['title'])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, $condition ? 'id' : 'symbol', 'title', ['symbol !=' => null])->asArray();
		$this->scalarQueries->findPairsBy(Article::class, $condition ? 'id' : 'title', 'title')->asArray();
	}

}
