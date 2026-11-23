<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use Shredio\DoctrineQueries\Criteria\AnyOf;
use Shredio\DoctrineQueries\Criteria\Criteria;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Tests\Entity\Article;
use Tests\Entity\Author;

class AnyOfCriteriaRuleCases
{

	public ScalarQueries $scalarQueries;
	public ArrayQueries $arrayQueries;
	public ObjectQueries $objectQueries;
	public DoctrineQueries $doctrineQueries;

	public function validGroups(int $id, ?string $title): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['title LIKE' => '%a%', 'symbol' => null])]);
		$this->arrayQueries->findBy(Article::class, ['id >' => $id, Criteria::anyOf(['author.name' => 'John'], ['hashTags.hashtag' => 'php'])]);
		$this->objectQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2, Criteria::anyOf(['title' => $title], ['author.role.name' => 'Admin'])])]);
		$this->objectQueries->findOneBy(Article::class, [Criteria::anyOf(['id' => $id])]);
		$this->scalarQueries->findOneBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2]), 'symbol !=' => null, Criteria::anyOf(['title' => 'a'], ['content' => 'b'])]);
		$this->scalarQueries->findIndexedBy(Article::class, 'id', [Criteria::anyOf(['id' => [1, 2]], ['title !=' => ['a', null]])]);
		$this->scalarQueries->findPairsBy(Article::class, 'id', 'title', [Criteria::anyOf(['id <=' => 1], ['id >=' => 3])]);
		$this->scalarQueries->findColumnValuesBy(Article::class, 'id', [Criteria::anyOf(['id' => 1], ['title NOT LIKE' => 'a%'])]);
		$this->scalarQueries->findSingleColumnValueBy(Article::class, 'id', [Criteria::anyOf(['id' => 1], ['id <>' => 2])]);
		$this->doctrineQueries->countBy(Article::class, [Criteria::anyOf(['id' => 1], ['author.name' => 'John'])]);
		$this->doctrineQueries->existsBy(Article::class, [Criteria::anyOf(['id' => 1], ['hashTags.article.title' => 'a'])]);
		$this->doctrineQueries->deleteBy(Article::class, ['symbol' => null, Criteria::anyOf(['id' => 1], ['author' => 2, Criteria::anyOf(['title' => 'a'], ['title' => 'b'])])]);
		$this->doctrineQueries->subQuery(Article::class, [Criteria::anyOf(['id' => 1], ['author.name' => 'John'])], select: ['id']);
		$this->scalarQueries->findBy(Article::class, [
			Criteria::anyOf(
				['author' => $this->doctrineQueries->subQuery(Author::class, [Criteria::anyOf(['name' => 'John'], ['role.name' => 'Admin'])], select: ['id'])],
				['id' => 1],
			),
		]);
	}

	public function validGroupInVariable(bool $onlyFirst): void
	{
		$group = Criteria::anyOf(['id' => 1], ['title' => 'a']);
		$this->scalarQueries->findBy(Article::class, [$group, 'symbol' => null]);

		$criteria = ['symbol' => null];
		$criteria[] = Criteria::anyOf(['id' => 1], ['title' => 'a']);
		$criteria[] = $group;
		$this->scalarQueries->findBy(Article::class, $criteria);

		$alternative = ['id' => 2, 'title' => 'b'];
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], $alternative)]);

		$conditional = $onlyFirst ? Criteria::anyOf(['id' => 1]) : Criteria::anyOf(['id' => 1], ['title' => 'a']);
		$this->scalarQueries->findBy(Article::class, [$conditional]);
	}

	public function validNamedAlternatives(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(first: ['id' => 1], second: ['title' => 'a'])]);
	}

	public function validManyAlternatives(): void
	{
		$this->scalarQueries->findBy(Article::class, [
			Criteria::anyOf(
				['id' => 1],
				['id >' => 2],
				['id <' => 3],
				['id >=' => 4],
				['id <=' => 5],
				['id !=' => 6],
				['title' => 'a'],
				['title LIKE' => 'b'],
				['title NOT LIKE' => 'c'],
				['title !=' => 'd'],
				['content' => 'e'],
				['content LIKE' => 'f'],
				['content NOT LIKE' => 'g'],
				['content !=' => 'h'],
				['symbol' => 'i'],
				['symbol !=' => 'j'],
				['symbol LIKE' => 'k'],
				['symbol NOT LIKE' => 'l'],
				['author' => 1],
				['author !=' => 2],
				['author.name' => 'm'],
				['author.name !=' => 'n'],
				['author.name LIKE' => 'o'],
				['author.role.name' => 'p'],
				['hashTags.hashtag' => 'q'],
			),
		]);
	}

	public function invalidFieldInAlternative(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['invalidField' => 1])]);
	}

	public function invalidFieldInFirstAlternativeAndCriteria(): void
	{
		$this->arrayQueries->findOneBy(Article::class, ['invalidCriteriaField' => 1, Criteria::anyOf(['invalidAlternativeField >' => 1, 'id' => 2], ['id' => 1])]);
	}

	public function sameInvalidFieldInEveryAlternativeIsReportedOnce(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['invalidField' => 1], ['invalidField' => 2], ['invalidField' => 3, 'id' => 1])]);
	}

	public function differentInvalidFieldsInAlternatives(): void
	{
		$this->objectQueries->findBy(Article::class, [Criteria::anyOf(['firstInvalidField' => 1], ['secondInvalidField' => 2])]);
	}

	public function invalidFieldInNestedGroup(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2, Criteria::anyOf(['title' => 'a'], ['nestedInvalidField' => 1])])]);
	}

	public function invalidFieldInSecondGroup(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2]), Criteria::anyOf(['id' => 3], ['invalidFieldOfSecondGroup' => 1])]);
	}

	public function invalidFieldInGroupInVariable(): void
	{
		$group = Criteria::anyOf(['id' => 1], ['invalidFieldInVariable' => 1]);
		$criteria = ['symbol' => null];
		$criteria[] = $group;

		$this->scalarQueries->findBy(Article::class, $criteria);
	}

	public function invalidRelatedFieldInAlternative(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['author.invalidRelatedField' => 1])]);
	}

	public function invalidAssociationInAlternative(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['title.length >' => 1], ['id' => 1])]);
	}

	public function toManyAssociationInAlternative(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['hashTags' => 1])]);
	}

	public function unsupportedOperatorInAlternative(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['id BETWEEN' => 1])]);
	}

	public function invalidFieldInEveryMethod(): void
	{
		$this->scalarQueries->findIndexedBy(Article::class, 'id', [Criteria::anyOf(['id' => 1], ['invalidField' => 1])]);
		$this->arrayQueries->findPairsBy(Article::class, 'id', 'title', [Criteria::anyOf(['id' => 1], ['invalidField' => 1])]);
		$this->scalarQueries->findColumnValuesBy(Article::class, 'id', [Criteria::anyOf(['id' => 1], ['invalidField' => 1])]);
		$this->scalarQueries->findSingleColumnValueBy(Article::class, 'id', [Criteria::anyOf(['id' => 1], ['invalidField' => 1])]);
		$this->objectQueries->findOneBy(Article::class, [Criteria::anyOf(['id' => 1], ['invalidField' => 1])]);
		$this->doctrineQueries->countBy(Article::class, [Criteria::anyOf(['id' => 1], ['invalidField' => 1])]);
		$this->doctrineQueries->existsBy(Article::class, [Criteria::anyOf(['id' => 1], ['invalidField' => 1])]);
		$this->doctrineQueries->deleteBy(Article::class, [Criteria::anyOf(['id' => 1], ['invalidField' => 1])]);
		$this->doctrineQueries->subQuery(Article::class, [Criteria::anyOf(['id' => 1], ['invalidField' => 1])], select: ['id']);
	}

	public function invalidFieldInGroupOfSubQuery(): void
	{
		$this->scalarQueries->findBy(Article::class, [
			Criteria::anyOf(
				['author' => $this->doctrineQueries->subQuery(Author::class, [Criteria::anyOf(['name' => 'John'], ['invalidAuthorField' => 1])], select: ['id'])],
				['id' => 1],
			),
		]);
	}

	public function deleteByRelatedCriteriaInAlternative(): void
	{
		$this->doctrineQueries->deleteBy(Article::class, [Criteria::anyOf(['id' => 1], ['author.name' => 'John'])]);
		$this->doctrineQueries->deleteBy(Article::class, ['id' => 1, Criteria::anyOf(['id' => 2], ['id' => 3, Criteria::anyOf(['title' => 'a'], ['hashTags.hashtag' => 'php'])])]);
	}

	public function itemWithoutField(): void
	{
		$this->scalarQueries->findBy(Article::class, ['title']);
		$this->scalarQueries->findBy(Article::class, ['id' => 1, ['title' => 'a']]);
		$this->doctrineQueries->deleteBy(Article::class, [Criteria::anyOf(['id' => 1]), 5 => null]);
	}

	public function itemWithoutFieldInAlternative(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2, 'title'])]);
	}

	public function groupComparedWithField(): void
	{
		$this->scalarQueries->findBy(Article::class, ['title' => Criteria::anyOf(['id' => 1], ['id' => 2])]);
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['author.name !=' => Criteria::anyOf(['id' => 2])])]);
	}

	/**
	 * @param array<string, mixed> $alternative
	 * @param list<array<string, mixed>> $alternatives
	 * @param AnyOf<array<string, mixed>> $group
	 */
	public function alternativesThatAreNotConstant(array $alternative, array $alternatives, AnyOf $group, AnyOf $groupWithoutType): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], $alternative)]);
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(...$alternatives)]);
		$this->scalarQueries->findBy(Article::class, [$group]);
		$this->scalarQueries->findBy(Article::class, [$groupWithoutType]);
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 2, Criteria::anyOf($alternative)])]);
	}

	public function alternativeWithoutCriteria(bool $withTitle): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], [])]);
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf([])]);
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf()]);

		$alternative = [];
		if ($withTitle) {
			$alternative['title'] = 'a';
		}

		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], $alternative)]);
	}

	public function alternativeWithOptionalCriteria(bool $withTitle): void
	{
		$alternative = ['id' => 2];
		if ($withTitle) {
			$alternative['title'] = 'a';
		}

		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], $alternative)]);
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ['id' => 1, 'title' => 'a'], ['id' => 1, 'title' => 'a', 'symbol' => null])]);
	}

	public function invalidFieldInOneOfConditionalGroups(bool $onlyFirst): void
	{
		$group = $onlyFirst ? Criteria::anyOf(['id' => 1]) : Criteria::anyOf(['id' => 1], ['invalidFieldOfLongerGroup' => 1]);
		$this->scalarQueries->findBy(Article::class, [$group]);

		$alternative = $onlyFirst ? ['id' => 1] : ['invalidFieldOfConditionalAlternative' => 1];
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 2], $alternative)]);
	}

	public function invalidFieldInUnpackedAlternatives(): void
	{
		$alternatives = [['id' => 1], ['invalidUnpackedField' => 1]];
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(...$alternatives)]);
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(['id' => 1], ...[['title' => 'a'], ['invalidInlineUnpackedField' => 1]])]);
	}

	public function invalidFieldInNamedAlternative(): void
	{
		$this->scalarQueries->findBy(Article::class, [Criteria::anyOf(first: ['id' => 1], second: ['invalidNamedField' => 1])]);
	}

	public function invalidFieldInTheLastOfManyAlternatives(): void
	{
		$this->scalarQueries->findBy(Article::class, [
			Criteria::anyOf(
				['id' => 1], ['id >' => 2], ['id <' => 3], ['id >=' => 4], ['id <=' => 5], ['id !=' => 6],
				['title' => 'a'], ['title LIKE' => 'b'], ['title NOT LIKE' => 'c'], ['title !=' => 'd'],
				['content' => 'e'], ['content LIKE' => 'f'], ['content NOT LIKE' => 'g'], ['content !=' => 'h'],
				['symbol' => 'i'], ['symbol !=' => 'j'], ['symbol LIKE' => 'k'], ['symbol NOT LIKE' => 'l'],
				['author' => 1], ['author !=' => 2], ['author.name' => 'm'], ['author.name !=' => 'n'],
				['author.name LIKE' => 'o'], ['author.role.name' => 'p'], ['invalidFieldOfLastAlternative' => 'q'],
			),
		]);
	}

}
