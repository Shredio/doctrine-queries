<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;

class DoctrineQueriesRuleCases
{

	public ScalarQueries $scalarQueries;
	public ArrayQueries $arrayQueries;
	public ObjectQueries $objectQueries;
	public DoctrineQueries $doctrineQueries;

	public function invalidEntityName(): void
	{
		$someVariable = 'Article';
		$this->scalarQueries->findBy($someVariable);
	}

	public function invalidEntityClass(): void
	{
		$this->scalarQueries->findBy('NonExistentEntity');
	}

	public function validCriteriaArgument(): void
	{
		$dynamicCriteria = ['title' => 'test'];
		$this->scalarQueries->findBy(Article::class, $dynamicCriteria);
	}

	public function invalidCriteriaField(): void
	{
		$this->scalarQueries->findBy(Article::class, ['invalidField' => 1]);
	}

	public function invalidOrderByField(): void
	{
		$this->scalarQueries->findBy(Article::class, [], ['invalidOrderField' => 'ASC']);
	}

	public function invalidSelectField(): void
	{
		$this->arrayQueries->findBy(Article::class, [], [], ['invalidSelectField']);
	}

	public function invalidFieldArgument(): void
	{
		$dynamicField = 'title';
		$this->scalarQueries->findSingleColumnValueBy(Article::class, $dynamicField, []);
	}

	public function invalidField(): void
	{
		$this->scalarQueries->findSingleColumnValueBy(Article::class, 'nonExistentField', []);
	}

	public function invalidNonConstantField(string $field = 'author'): void
	{
		$dynamicCriteria = ['title' => 'test'];
		$dynamicCriteria[$field] = 1;

		$this->scalarQueries->findBy(Article::class, $dynamicCriteria);
	}

	public function invalidFieldInVariable(): void
	{
		$dynamicCriteria = ['title' => 'test'];
		$field = 'invalidField';
		$dynamicCriteria[$field] = 1;

		$this->scalarQueries->findBy(Article::class, $dynamicCriteria);
	}

	public function invalidDynamicArgumentBuilder(): void
	{
		$dynamicCriteria = ['title' => 'test'];
		$dynamicCriteria['author'] = 1;

		if (mt_rand(0, 1) === 1) {
			$dynamicCriteria['invalidField'] = null;
		}

		$this->scalarQueries->findBy(Article::class, $dynamicCriteria);
	}

	public function invalidTwoFields(): void
	{
		$dynamicCriteria = ['title' => 'test'];
		$dynamicCriteria['author_id'] = 1;
		$dynamicCriteria['symbol_id'] = null;

		$this->scalarQueries->findBy(Article::class, $dynamicCriteria);
	}

	public function invalidSubQueryField(): void
	{
		$this->doctrineQueries->subQuery(Article::class, ['authorId' => 1]);
	}

	public function invalidJoinConfig(): void
	{
		$this->doctrineQueries->arrays->findBy(Article::class, joinConfig: ['auth' => 'inner']);
		$this->doctrineQueries->arrays->findOneBy(Article::class, joinConfig: ['author.rol' => 'inner']);
		$this->doctrineQueries->arrays->findIndexedBy(Article::class, 'id', joinConfig: ['auth' => 'inner']);
		$this->doctrineQueries->arrays->findPairsBy(Article::class, 'id', 'id', joinConfig: ['author.rol' => 'inner']);

		$this->doctrineQueries->scalars->findBy(Article::class, joinConfig: ['auth' => 'inner']);
		$this->doctrineQueries->scalars->findOneBy(Article::class, joinConfig: ['author.rol' => 'inner']);
		$this->doctrineQueries->scalars->findIndexedBy(Article::class, 'id', joinConfig: ['auth' => 'inner']);
		$this->doctrineQueries->scalars->findPairsBy(Article::class, 'id', 'id', joinConfig: ['author.rol' => 'inner']);
	}

	public function validCases(): void
	{
		$this->scalarQueries->findBy(Article::class, ['title' => 'Test']);
		$this->scalarQueries->findBy(Article::class, [], ['title' => 'ASC']);
		$this->arrayQueries->findBy(Article::class, [], [], ['title', 'content']);
		$this->scalarQueries->findSingleColumnValueBy(Article::class, 'title', []);
		$this->doctrineQueries->countBy(Article::class, ['symbol' => null]);
		$this->doctrineQueries->existsBy(Article::class, ['id' => 1]);
		$this->doctrineQueries->deleteBy(Article::class, ['symbol' => null]);
		$this->objectQueries->findBy(Article::class, ['author' => 1]);
		$this->objectQueries->findOneBy(Article::class, ['author' => 1]);
		$this->doctrineQueries->subQuery(Article::class, ['author' => 1]);
	}

	public function validDynamicArgumentBuilder(): void
	{
		$dynamicCriteria = ['title' => 'test'];
		$dynamicCriteria['author'] = 1;

		if (mt_rand(0, 1) === 1) {
			$dynamicCriteria['symbol'] = null;
		}

		$this->scalarQueries->findBy(Article::class, $dynamicCriteria);
	}

	public function validJoinConfig(): void
	{
		$this->doctrineQueries->arrays->findBy(Article::class, joinConfig: ['author' => 'inner', 'author.role' => 'left']);
		$this->doctrineQueries->arrays->findOneBy(Article::class, joinConfig: ['author' => 'inner', 'author.role' => 'left']);
		$this->doctrineQueries->arrays->findIndexedBy(Article::class, 'id', joinConfig: ['author' => 'inner', 'author.role' => 'left']);
		$this->doctrineQueries->arrays->findPairsBy(Article::class, 'id', 'id', joinConfig: ['author' => 'inner', 'author.role' => 'left']);

		$this->doctrineQueries->scalars->findBy(Article::class, joinConfig: ['author' => 'inner', 'author.role' => 'left']);
		$this->doctrineQueries->scalars->findOneBy(Article::class, joinConfig: ['author' => 'inner', 'author.role' => 'left']);
		$this->doctrineQueries->scalars->findIndexedBy(Article::class, 'id', joinConfig: ['author' => 'inner', 'author.role' => 'left']);
		$this->doctrineQueries->scalars->findPairsBy(Article::class, 'id', 'id', joinConfig: ['author' => 'inner', 'author.role' => 'left']);
	}

}
