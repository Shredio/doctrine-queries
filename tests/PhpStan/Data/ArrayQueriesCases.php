<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Query\ArrayQueries;
use Tests\Entity\Article;
use int;
use function PHPStan\Testing\assertType;

class ArrayQueriesCases
{

	public function testFindBy(ArrayQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string, content: string, symbol: Tests\Doctrine\Symbol|null, createdAt: DateTimeImmutable, type: Tests\Entity\Enum\ArticleType}>', $queries->findBy(Article::class));
	}

	public function testFindOneBy(ArrayQueries $queries): void
	{
		assertType('array{id: int, title: string, content: string, symbol: Tests\Doctrine\Symbol|null, createdAt: DateTimeImmutable, type: Tests\Entity\Enum\ArticleType}|null', $queries->findOneBy(Article::class));
	}

	public function testFindByYield(ArrayQueries $queries): void
	{
		assertType('iterable<int, array{id: int, title: string, content: string, symbol: Tests\Doctrine\Symbol|null, createdAt: DateTimeImmutable, type: Tests\Entity\Enum\ArticleType}>', $queries->findBy(Article::class)->yield());
	}

	public function testFindByAsArray(ArrayQueries $queries): void
	{
		assertType('list<array{id: int, title: string, content: string, symbol: Tests\Doctrine\Symbol|null, createdAt: DateTimeImmutable, type: Tests\Entity\Enum\ArticleType}>', $queries->findBy(Article::class)->asArray());
	}

	public function testFindByWithCriteria(ArrayQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: 1, title: string, content: string, symbol: Tests\Doctrine\Symbol, createdAt: DateTimeImmutable, type: Tests\Entity\Enum\ArticleType}>', $queries->findBy(Article::class, ['id' => 1, 'symbol !=' => null]));
	}

	public function testFindByWithSelect(ArrayQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string}>', $queries->findBy(Article::class, [], [], ['id', 'title']));
	}

	public function testFindByWithRelations(ArrayQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseResults<array{id: int, title: string, content: string, symbol: Tests\Doctrine\Symbol|null, createdAt: DateTimeImmutable, type: Tests\Entity\Enum\ArticleType, author: int}>', $queries->findByWithRelations(Article::class));
	}

	public function testFindByWithRelationsYield(ArrayQueries $queries): void
	{
		assertType('iterable<int, array{id: int, title: string, content: string, symbol: Tests\Doctrine\Symbol|null, createdAt: DateTimeImmutable, type: Tests\Entity\Enum\ArticleType, author: int}>', $queries->findByWithRelations(Article::class)->yield());
	}

	public function testFindByWithRelationsAsArray(ArrayQueries $queries): void
	{
		assertType('list<array{id: int, title: string, content: string, symbol: Tests\Doctrine\Symbol|null, createdAt: DateTimeImmutable, type: Tests\Entity\Enum\ArticleType, author: int}>', $queries->findByWithRelations(Article::class)->asArray());
	}

	public function testFindPairsBy(ArrayQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabasePairs<int, string>', $queries->findPairsBy(Article::class, 'id', 'title'));
		assertType('Shredio\DoctrineQueries\Result\DatabasePairs<int, Tests\Doctrine\Symbol|null>', $queries->findPairsBy(Article::class, 'id', 'symbol'));
		assertType('Shredio\DoctrineQueries\Result\DatabasePairs<int, int>', $queries->findPairsBy(Article::class, 'id', 'author'));
	}

	public function testFindPairsByYield(ArrayQueries $queries): void
	{
		assertType('iterable<int, string>', $queries->findPairsBy(Article::class, 'id', 'title')->yield());
		assertType('iterable<int, Tests\Doctrine\Symbol|null>', $queries->findPairsBy(Article::class, 'id', 'symbol')->yield());
		assertType('iterable<int, int>', $queries->findPairsBy(Article::class, 'id', 'author')->yield());
	}

	public function testFindPairsByAsArray(ArrayQueries $queries): void
	{
		assertType('array<int, string>', $queries->findPairsBy(Article::class, 'id', 'title')->asArray());
		assertType('array<int, Tests\Doctrine\Symbol|null>', $queries->findPairsBy(Article::class, 'id', 'symbol')->asArray());
		assertType('array<int, int>', $queries->findPairsBy(Article::class, 'id', 'author')->asArray());
	}

	public function testFindColumnValuesBy(ArrayQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseColumnValues<string>', $queries->findColumnValuesBy(Article::class, 'title'));
		assertType('Shredio\DoctrineQueries\Result\DatabaseColumnValues<int>', $queries->findColumnValuesBy(Article::class, 'id'));
		assertType('Shredio\DoctrineQueries\Result\DatabaseColumnValues<Tests\Doctrine\Symbol|null>', $queries->findColumnValuesBy(Article::class, 'symbol'));
		assertType('Shredio\DoctrineQueries\Result\DatabaseColumnValues<int>', $queries->findColumnValuesBy(Article::class, 'author'));
	}

	public function testFindColumnValuesByYield(ArrayQueries $queries): void
	{
		assertType('iterable<int, string>', $queries->findColumnValuesBy(Article::class, 'title')->yield());
		assertType('iterable<int, int>', $queries->findColumnValuesBy(Article::class, 'id')->yield());
		assertType('iterable<int, Tests\Doctrine\Symbol|null>', $queries->findColumnValuesBy(Article::class, 'symbol')->yield());
		assertType('iterable<int, int>', $queries->findColumnValuesBy(Article::class, 'author')->yield());
	}

	public function testFindColumnValuesByAsArray(ArrayQueries $queries): void
	{
		assertType('list<string>', $queries->findColumnValuesBy(Article::class, 'title')->asArray());
		assertType('list<int>', $queries->findColumnValuesBy(Article::class, 'id')->asArray());
		assertType('list<Tests\Doctrine\Symbol|null>', $queries->findColumnValuesBy(Article::class, 'symbol')->asArray());
		assertType('list<int>', $queries->findColumnValuesBy(Article::class, 'author')->asArray());
	}

	public function testFindSingleColumnValueBy(ArrayQueries $queries): void
	{
		assertType('string|null', $queries->findSingleColumnValueBy(Article::class, 'title', []));
		assertType('int|null', $queries->findSingleColumnValueBy(Article::class, 'id', []));
		assertType('Tests\Doctrine\Symbol|null', $queries->findSingleColumnValueBy(Article::class, 'symbol', ['symbol !=' => null]));
		assertType('int|null', $queries->findSingleColumnValueBy(Article::class, 'author', []));
	}

	public function testFindSingleColumnValueByWithCriteria(ArrayQueries $queries): void
	{
		assertType('string|null', $queries->findSingleColumnValueBy(Article::class, 'title', ['id' => 1]));
		assertType('int|null', $queries->findSingleColumnValueBy(Article::class, 'author', ['id' => 1]));
	}

}
