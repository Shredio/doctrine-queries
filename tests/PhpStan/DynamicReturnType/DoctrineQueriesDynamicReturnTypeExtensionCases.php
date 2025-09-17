<?php declare(strict_types = 1);

namespace Tests\PhpStan\DynamicReturnType;

use Shredio\DoctrineQueries\DoctrineQueries;
use Tests\Entity\Article;
use function PHPStan\Testing\assertType;

final readonly class DoctrineQueriesDynamicReturnTypeExtensionCases
{

	public function testStaticTypedArray(DoctrineQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<array{id: int}>', $queries->existsManyBy(Article::class, [
			['id' => 1],
			['id' => 2],
		]));
	}

	public function testStaticTypedDoubleKeyArray(DoctrineQueries $queries): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<array{id: int, name: string}>', $queries->existsManyBy(Article::class, [
			['id' => 1, 'name' => 'A'],
			['id' => 2, 'name' => 'B'],
		]));
	}

	/**
	 * @param list<array{ id: string }> $values
	 */
	public function testConstantArray(DoctrineQueries $queries, array $values): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<array{id: string}>', $queries->existsManyBy(Article::class, $values));
	}

	/**
	 * @param list<array<string, mixed>> $values
	 */
	public function testDynamicArray(DoctrineQueries $queries, array $values): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<*NEVER*>', $queries->existsManyBy(Article::class, $values));
	}

	public function testNoArray(DoctrineQueries $queries, mixed $value): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<*NEVER*>', $queries->existsManyBy(Article::class, $value));
	}

	/**
	 * @param array<array{ id: non-empty-string }> $values
	 */
	public function testNonEmptyString(DoctrineQueries $queries, array $values): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<array{id: non-empty-string}>', $queries->existsManyBy(Article::class, $values));
	}

	/**
	 * @param array<array{ id: 'name' }> $values
	 */
	public function testDowngradeConstantString(DoctrineQueries $queries, array $values): void
	{
		assertType('Shredio\DoctrineQueries\Result\DatabaseExistenceResults<array{id: string}>', $queries->existsManyBy(Article::class, $values));
	}

}
