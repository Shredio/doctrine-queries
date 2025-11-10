<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;
use Tests\Entity\ArticleNullableAuthor;
use Tests\Entity\FavouritePrompt;
use Tests\Entity\OneToOnePrimary;
use function PHPStan\Testing\assertType;

class JoinScalarQueriesCases
{

	public function testFindPairsByExcludeNull(ScalarQueries $queries): void
	{
		assertType('array<int, string>', $queries->findPairsBy(OneToOnePrimary::class, 'assoc.id', 'assoc.nullableField', [
			'assoc.nullableField !=' => null,
		])->asArray());
	}

	/**
	 * @param string[] $values
	 */
	public function testFindPairsByListOfValues(ScalarQueries $queries, array $values): void
	{
		assertType('array<int, string>', $queries->findPairsBy(OneToOnePrimary::class, 'assoc.id', 'assoc.nullableField', [
			'assoc.nullableField' => $values,
		])->asArray());
	}

}
