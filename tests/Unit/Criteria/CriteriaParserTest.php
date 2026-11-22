<?php declare(strict_types = 1);

namespace Tests\Unit\Criteria;

use ArrayIterator;
use Doctrine\ORM\Query\Parameter;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Criteria\ParsedCriteria;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Select\QueryType;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\TestCase;

final class CriteriaParserTest extends TestCase
{

	use DoctrineContext;

	public function testEmptyCriteria(): void
	{
		$this->assertSame([], $this->parse([]));
	}

	/**
	 * @return iterable<string, array{string, mixed, string, string}>
	 */
	public static function provideOperatorCriteria(): iterable
	{
		yield 'implicit equals' => ['field', 'value', 'field', '='];
		yield 'explicit equals' => ['field =', 'value', 'field', '='];
		yield 'not equals' => ['field !=', 'value', 'field', '!='];
		yield 'not equals as <>' => ['field <>', 'value', 'field', '!='];
		yield 'greater than' => ['field >', 1, 'field', '>'];
		yield 'greater than or equal' => ['field >=', 1, 'field', '>='];
		yield 'less than' => ['field <', 1, 'field', '<'];
		yield 'less than or equal' => ['field <=', 1, 'field', '<='];
		yield 'like' => ['field LIKE', '%a%', 'field', 'LIKE'];
		yield 'not like' => ['field NOT LIKE', '%a%', 'field', 'NOT LIKE'];
		yield 'lower case like' => ['field like', '%a%', 'field', 'LIKE'];
		yield 'extra whitespace' => ['field  NOT   LIKE ', '%a%', 'field', 'NOT LIKE'];
		yield 'related field' => ['author.role.name', 'Admin', 'author.role.name', '='];
		yield 'empty string value' => ['field', '', 'field', '='];
	}

	#[DataProvider('provideOperatorCriteria')]
	public function testOperator(string $criteriaKey, mixed $value, string $expectedSelector, string $expectedOperator): void
	{
		[$parsed] = $this->parse([$criteriaKey => $value]);

		$this->assertSame($expectedSelector, $parsed->field->selector);
		$this->assertSame($expectedOperator, $parsed->operator);
		$this->assertSame(':param_1', $parsed->operand);
		$this->assertSame('param_1', $parsed->parameterName);
		$this->assertSame($value, $parsed->value);
		$this->assertNull($parsed->parameters);
	}

	public function testParameterNamesAreNumberedAndSuffixed(): void
	{
		$parsed = $this->parse(['first' => 1, 'second' => null, 'third >' => 3], suffix: 'e0');

		$this->assertSame(['param_e0_1', null, 'param_e0_2'], array_map(static fn (ParsedCriteria $item): ?string => $item->parameterName, $parsed));
		$this->assertSame([':param_e0_1', null, ':param_e0_2'], array_map(static fn (ParsedCriteria $item): ?string => $item->operand, $parsed));
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function provideNullCriteria(): iterable
	{
		yield 'equals' => ['field', 'IS NULL'];
		yield 'not equals' => ['field !=', 'IS NOT NULL'];
		yield 'not equals as <>' => ['field <>', 'IS NOT NULL'];
	}

	#[DataProvider('provideNullCriteria')]
	public function testNullValue(string $criteriaKey, string $expectedOperator): void
	{
		[$parsed] = $this->parse([$criteriaKey => null]);

		$this->assertSame($expectedOperator, $parsed->operator);
		$this->assertNull($parsed->operand);
		$this->assertNull($parsed->parameterName);
		$this->assertNull($parsed->value);
	}

	/**
	 * @return iterable<string, array{string, iterable<mixed>, string, list<mixed>}>
	 */
	public static function provideIterableCriteria(): iterable
	{
		yield 'array' => ['field', [1, 2], 'IN', [1, 2]];
		yield 'array not in' => ['field !=', [1, 2], 'NOT IN', [1, 2]];
		yield 'empty array' => ['field', [], 'IN', []];
		yield 'keys are dropped from traversables' => ['field', new ArrayIterator(['a' => 1, 'b' => 2]), 'IN', [1, 2]];
		yield 'generator' => ['field', self::generateValues(), 'IN', [1, 2, 3]];
	}

	/**
	 * @param iterable<mixed> $value
	 * @param list<mixed> $expectedValue
	 */
	#[DataProvider('provideIterableCriteria')]
	public function testIterableValue(string $criteriaKey, iterable $value, string $expectedOperator, array $expectedValue): void
	{
		[$parsed] = $this->parse([$criteriaKey => $value]);

		$this->assertSame($expectedOperator, $parsed->operator);
		$this->assertSame('(:param_1)', $parsed->operand);
		$this->assertSame($expectedValue, $parsed->value);
	}

	/**
	 * @return iterable<string, array{string, list<mixed>, string, ?string, ?list<mixed>, bool}>
	 */
	public static function provideListsWithNull(): iterable
	{
		yield 'in' => ['field', [1, null, 2], 'IN', '(:param_1)', [1, 2], true];
		yield 'in only null' => ['field', [null], 'IS NULL', null, null, false];
		yield 'not in' => ['field !=', [1, null], 'NOT IN', '(:param_1)', [1], false];
		yield 'not in only null' => ['field !=', [null, null], 'IS NOT NULL', null, null, false];
	}

	/**
	 * @param list<mixed> $value
	 * @param list<mixed>|null $expectedValue
	 */
	#[DataProvider('provideListsWithNull')]
	public function testListWithNull(string $criteriaKey, array $value, string $expectedOperator, ?string $expectedOperand, ?array $expectedValue, bool $expectedOrIsNull): void
	{
		[$parsed] = $this->parse([$criteriaKey => $value]);

		$this->assertSame($expectedOperator, $parsed->operator);
		$this->assertSame($expectedOperand, $parsed->operand);
		$this->assertSame($expectedValue, $parsed->value);
		$this->assertSame($expectedOrIsNull, $parsed->orIsNull);
	}

	public function testEmptyNotInIsSkipped(): void
	{
		$parsed = $this->parse(['first !=' => [], 'second' => 1]);

		$this->assertCount(1, $parsed);
		$this->assertSame('second', $parsed[0]->field->selector);
		$this->assertSame('param_1', $parsed[0]->parameterName);
	}

	/**
	 * @return iterable<string, array{string, mixed}>
	 */
	public static function provideUnsupportedOperatorWithValue(): iterable
	{
		yield 'array with >' => ['field >', [1, 2]];
		yield 'array with LIKE' => ['field LIKE', ['%a%']];
		yield 'null with >' => ['field >', null];
		yield 'null with LIKE' => ['field LIKE', null];
	}

	#[DataProvider('provideUnsupportedOperatorWithValue')]
	public function testOperatorNotAllowedWithValue(string $criteriaKey, mixed $value): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Only = and != operators are allowed');

		$this->parse([$criteriaKey => $value]);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideUnsupportedOperators(): iterable
	{
		yield 'unknown word' => ['field BETWEEN'];
		yield 'explicit IN' => ['field IN'];
		yield 'DQL injection' => ['id IN (1) OR e0.id ='];
		yield 'double equals' => ['field =='];
	}

	#[DataProvider('provideUnsupportedOperators')]
	public function testUnsupportedOperator(string $criteriaKey): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage(sprintf('in criteria "%s"', $criteriaKey));

		$this->parse([$criteriaKey => 1]);
	}

	public function testUnsupportedOperatorMessageListsSupportedOperators(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported operator "BETWEEN" in criteria "field BETWEEN". Supported operators are: =, !=, <>, <, <=, >, >=, LIKE, NOT LIKE.');

		$this->parse(['field BETWEEN' => 1]);
	}

	public function testEmptyField(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Field cannot be empty');

		$this->parse(['' => 'value']);
	}

	public function testQueryBuilderValue(): void
	{
		$queryBuilder = $this->getEntityManager()->createQueryBuilder()
			->select('a.id')
			->from(Author::class, 'a')
			->where('a.name = :name')
			->setParameter('name', 'John Doe');

		[$in] = $this->parse(['author' => $queryBuilder]);
		[$notIn] = $this->parse(['author !=' => $queryBuilder->getQuery()]);

		$this->assertSame(sprintf('IN(%s)', $queryBuilder->getDQL()), $in->operator);
		$this->assertSame(sprintf('NOT IN(%s)', $queryBuilder->getDQL()), $notIn->operator);

		foreach ([$in, $notIn] as $parsed) {
			$this->assertNull($parsed->operand);
			$this->assertNull($parsed->parameterName);
			$this->assertNotNull($parsed->parameters);
			$this->assertSame(['name' => 'John Doe'], $this->parametersToArray($parsed->parameters->toArray()));
		}
	}

	public function testQueryBuilderValueWithUnsupportedOperator(): void
	{
		$queryBuilder = $this->getEntityManager()->createQueryBuilder()->select('a.id')->from(Author::class, 'a');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Only = and != operators are allowed with QueryBuilder or Query value');

		$this->parse(['author >' => $queryBuilder]);
	}

	public function testSubQueryRequiresQueryMetadata(): void
	{
		$subQuery = (new DoctrineQueries($this->createManagerRegistry()))->subQuery(Author::class, select: ['id']);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('QueryMetadata is required when using SubQuery');

		$this->parse(['author' => $subQuery]);
	}

	public function testSubQueryIsBuiltWithParentMetadata(): void
	{
		$subQuery = (new DoctrineQueries($this->createManagerRegistry()))->subQuery(Author::class, ['name' => 'John Doe'], select: ['id']);
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		[$parsed] = iterator_to_array(CriteriaParser::parse(['author' => $subQuery], $metadata->getRootAlias(), $metadata), false);

		// The sub-query allocates its aliases from the parent counter, so they never clash with the parent ones.
		$this->assertSame('IN(SELECT e1.id FROM Tests\Entity\Author e1 WHERE e1.name = :param_e1_1)', $parsed->operator);
		$this->assertNotNull($parsed->parameters);
		$this->assertSame(['param_e1_1' => 'John Doe'], $this->parametersToArray($parsed->parameters->toArray()));
	}

	/**
	 * @param array<string, mixed> $criteria
	 * @return list<ParsedCriteria>
	 */
	private function parse(array $criteria, string $suffix = ''): array
	{
		return iterator_to_array(CriteriaParser::parse($criteria, $suffix), false);
	}

	/**
	 * @param array<int, Parameter> $parameters
	 * @return array<string, mixed>
	 */
	private function parametersToArray(array $parameters): array
	{
		$values = [];
		foreach ($parameters as $parameter) {
			$values[$parameter->getName()] = $parameter->getValue();
		}

		return $values;
	}

	/**
	 * @return Generator<int, int>
	 */
	private static function generateValues(): Generator
	{
		yield 1;
		yield 2;
		yield 3;
	}

	protected function setUpDatabase(): bool
	{
		return false;
	}

}
