<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Pagination\Pagination;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use SortDirection;
use Tests\Doctrine\Symbol;
use Tests\Entity\Article;
use Tests\Entity\Author;

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
		$this->scalarQueries->findBy(Article::class, orderBy: ['invalidOrderField' => SortDirection::Ascending]);
	}

	public function invalidSelectField(): void
	{
		$this->arrayQueries->findBy(Article::class, select: ['invalidSelectField']);
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

	public function requireNamedArguments(): void
	{
		$this->scalarQueries->findBy(Article::class, ['title' => 'Test'], ['title' => SortDirection::Ascending], ['title'], null, 'inner');
	}

	public function invalidFieldNameInExistsManyBy(): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, [
			['invalidField' => 1],
			['invalidField' => 2],
		]);
	}

	/**
	 * @param list<array{ invalidField: int }> $values
	 */
	public function invalidExistsManyByConstantArray(array $values): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, $values);
	}

	/**
	 * @param iterable<array{ invalidField: int }> $values
	 */
	public function invalidExistsManyByConstantIterable(iterable $values): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, $values);
	}

	public function invalidPreloadFieldUnknown(): void
	{
		$this->doctrineQueries->objects->findBy(Article::class, preload: ['unknownField']);
	}

	public function invalidPreloadFieldAssociation(): void
	{
		$this->doctrineQueries->objects->findBy(Article::class, preload: ['author.nonExistentAssociation']);
	}

	public function invalidPreloadFieldNonAssociation(): void
	{
		$this->doctrineQueries->objects->findBy(Article::class, preload: ['title']);
	}

	public function validPreloadFields(): void
	{
		$this->doctrineQueries->objects->findBy(Article::class, preload: ['author', 'author.role']);
	}

	/**
	 * @param list<array{ id: Symbol }> $values
	 */
	public function validExistsManyByNonScalarTypeInCriteria(array $values): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, $values);
	}

	/**
	 * @param list<array{ id: int }> $values
	 */
	public function validExistsManyByConstantArray(array $values): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, $values);
	}

	/**
	 * @param iterable<array{ id: int }> $values
	 */
	public function validExistsManyByConstantIterable(iterable $values): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, $values);
	}

	public function validCases(): void
	{
		$this->scalarQueries->findBy(Article::class, ['title' => 'Test']);
		$this->scalarQueries->findBy(Article::class, orderBy: ['title' => SortDirection::Ascending]);
		$this->arrayQueries->findBy(Article::class, select: ['title', 'content']);
		$this->scalarQueries->findSingleColumnValueBy(Article::class, 'title', []);
		$this->doctrineQueries->countBy(Article::class, ['symbol' => null]);
		$this->doctrineQueries->existsBy(Article::class, ['id' => 1]);
		$this->doctrineQueries->deleteBy(Article::class, ['symbol' => null]);
		$this->objectQueries->findBy(Article::class, ['author' => 1]);
		$this->objectQueries->findOneBy(Article::class, ['author' => 1]);
		$this->doctrineQueries->subQuery(Article::class, ['author' => 1]);
		$this->scalarQueries->findBy(Article::class, select: ['**']);
		$this->scalarQueries->findBy(Article::class, select: ['*']);
		$this->scalarQueries->findBy(Article::class, select: ['author.**']);
		$this->scalarQueries->findBy(Article::class, select: ['author.*']);
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

	public function invalidCountByField(): void
	{
		$this->doctrineQueries->countBy(Article::class, field: 'nonExistentField');
		$this->doctrineQueries->countBy(Article::class, joinConfig: ['auth' => 'inner']);
	}

	public function validCountBy(): void
	{
		$this->doctrineQueries->countBy(Article::class, field: null);
		$this->doctrineQueries->countBy(Article::class, field: 'author.id', distinct: true, joinConfig: ['author' => 'inner']);
	}

	public function invalidObjectQueriesJoinConfig(): void
	{
		$this->objectQueries->findBy(Article::class, joinConfig: ['auth' => 'inner']);
		$this->objectQueries->findOneBy(Article::class, joinConfig: ['author.rol' => 'inner']);
	}

	public function invalidExistsManyByNamedArgument(): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, values: [['invalidField' => 1]]);
	}

	public function invalidFindPairsByNamedArguments(): void
	{
		$this->arrayQueries->findPairsBy(Article::class, key: 'unknownKey', value: 'title');
		$this->arrayQueries->findPairsBy(Article::class, value: 'unknownValue', key: 'id');
	}

	public function requireNamedArgumentsAfterDistinct(): void
	{
		$this->scalarQueries->findColumnValuesBy(Article::class, 'id', [], [], false, new Pagination(1), 'inner');
	}

	public function unsupportedCriteriaOperator(): void
	{
		$this->scalarQueries->findBy(Article::class, ['title BETWEEN' => 1]);
		$this->doctrineQueries->countBy(Article::class, ['id IN (1) OR e0.id =' => 1]);
	}

	public function validCriteriaOperators(): void
	{
		$this->scalarQueries->findBy(Article::class, [
			'id =' => 1,
			'id !=' => 2,
			'id <>' => 3,
			'id <' => 4,
			'id <=' => 5,
			'id >' => 0,
			'id >=' => 0,
			'title LIKE' => '%a%',
			'title not like' => '%b%',
		]);
	}

	public function deleteByRelatedCriteria(): void
	{
		$this->doctrineQueries->deleteBy(Article::class, ['author.name' => 'John Doe']);
	}

	public function validDeleteBy(): void
	{
		$this->doctrineQueries->deleteBy(Article::class, ['author' => 1]);
		$this->doctrineQueries->deleteBy(Article::class, ['author' => $this->doctrineQueries->subQuery(Author::class, ['role.name' => 'Administrator'])]);
	}

	public function invalidAssociationPath(): void
	{
		$this->scalarQueries->findBy(Article::class, ['title.length' => 1]);
		$this->scalarQueries->findBy(Article::class, select: ['author.rol.name']);
	}

	public function toManyAssociation(): void
	{
		$this->scalarQueries->findColumnValuesBy(Article::class, 'hashTags');
		$this->scalarQueries->findBy(Article::class, ['hashTags' => 1]);
	}

	public function validToManyAssociationFields(): void
	{
		$this->scalarQueries->findBy(Article::class, ['hashTags.hashtag' => 'php'], select: ['id']);
		$this->doctrineQueries->countBy(Article::class, ['hashTags.hashtag' => 'php']);
	}


	public function nonConstantEntityName(string $entity): void
	{
		$this->scalarQueries->findBy($entity);
	}

	/**
	 * @param array<string, SortDirection> $orderBy
	 * @param list<string> $select
	 * @param array<string, 'left'|'inner'> $joinConfig
	 * @param list<string> $preload
	 */
	public function nonConstantArrays(array $orderBy, array $select, array $joinConfig, array $preload): void
	{
		$this->scalarQueries->findBy(Article::class, orderBy: $orderBy);
		$this->scalarQueries->findBy(Article::class, select: $select);
		$this->scalarQueries->findBy(Article::class, joinConfig: $joinConfig);
		$this->objectQueries->findBy(Article::class, preload: $preload);
	}

	public function nonConstantFieldArgument(string $field): void
	{
		$this->scalarQueries->findColumnValuesBy(Article::class, $field);
		$this->arrayQueries->findIndexedBy(Article::class, $field);
		$this->doctrineQueries->countBy(Article::class, field: $field);
	}

	public function invalidAssociationPathInJoinConfigAndPreload(): void
	{
		$this->scalarQueries->findBy(Article::class, joinConfig: ['title.length' => 'inner']);
		$this->objectQueries->findBy(Article::class, preload: ['title.length']);
	}

	/**
	 * @param list<array<string, int>> $values
	 * @param iterable<mixed> $rows
	 */
	public function existsManyByNonConstantRows(array $values, iterable $rows): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, $values);
		$this->doctrineQueries->existsManyBy(Article::class, $rows);
	}

	public function existsManyByDifferentKeys(): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, [['id' => 1, 'title' => 'a'], ['id' => 2]]);
		$this->doctrineQueries->existsManyBy(Article::class, [['id' => 1], ['id' => 2, 'title' => 'b']]);
	}

	public function existsManyByRelatedField(): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, [['author.name' => 'John Doe']]);
	}

	public function validExistsManyBy(): void
	{
		$this->doctrineQueries->existsManyBy(Article::class, [['id' => 1, 'author' => 1], ['author' => 2, 'id' => 2]]);
		$this->doctrineQueries->existsManyBy(Article::class, [['id' => 1, 'author' => 1], ['id' => 2, 'author' => 2], ['id' => 3, 'author' => 1]]);
		$this->doctrineQueries->existsManyBy(Article::class, []);
		$this->doctrineQueries->existsManyBy(Article::class);
	}

	public function wildcardAsSingleField(): void
	{
		$this->scalarQueries->findColumnValuesBy(Article::class, '*');
		$this->scalarQueries->findSingleColumnValueBy(Article::class, 'author.*', []);
		$this->arrayQueries->findPairsBy(Article::class, '**', 'id');
		$this->arrayQueries->findPairsBy(Article::class, 'id', 'author.**');
		$this->arrayQueries->findIndexedBy(Article::class, '*');
		$this->doctrineQueries->countBy(Article::class, field: '*');
	}


	public function subQuerySelection(): void
	{
		$this->doctrineQueries->subQuery(Author::class, select: ['id', 'name']);
		$this->doctrineQueries->subQuery(Author::class, select: ['*']);
		$this->doctrineQueries->subQuery(Author::class, select: ['id', 'unknown']);
		$this->doctrineQueries->subQuery(Author::class);
		$this->doctrineQueries->subQuery(Author::class, select: []);
		$this->doctrineQueries->subQuery(Author::class, select: ['id']);
		$this->doctrineQueries->subQuery(Author::class, select: ['role']);
		$this->doctrineQueries->subQuery(Author::class, select: ['id' => 'authorId']);
	}

	public function selectAlias(): void
	{
		$this->arrayQueries->findBy(Article::class, select: ['title' => 'articleTitle', 'author.*' => 'author_']);
		$this->arrayQueries->findBy(Article::class, select: ['title' => 't, e0.content AS c']);
		$this->scalarQueries->findOneBy(Article::class, select: ['author.*' => 'author ']);
		$this->scalarQueries->findIndexedBy(Article::class, 'id', select: ['title' => '1title']);
	}

	public function singleColumnValueOrderAndJoins(): void
	{
		$this->scalarQueries->findSingleColumnValueBy(Article::class, 'title', [], orderBy: ['id' => SortDirection::Descending], joinConfig: 'inner');
		$this->arrayQueries->findSingleColumnValueBy(Article::class, 'title', [], orderBy: ['invalidOrderField' => SortDirection::Ascending]);
		$this->scalarQueries->findSingleColumnValueBy(Article::class, 'title', [], joinConfig: ['author' => 'inner']);
	}

	/**
	 * Calls that are not checked, the queries would be reported with the same arguments.
	 */
	public function uncheckedCalls(mixed $unknown, UnrelatedFinder $unrelatedFinder, string $method): void
	{
		$this->scalarQueries->findBy();
		$unknown->findBy('NonExistentEntity', ['invalidField' => 1]);
		$unrelatedFinder->findBy('NonExistentEntity', ['invalidField' => 1]);
		$this->scalarQueries->$method(Article::class, ['invalidField' => 1]);
		$this->doctrineQueries->getLastId(Article::class);
	}

}
