<?php declare(strict_types = 1);

namespace Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shredio\DoctrineQueries\PhpStan\PhpStanAnalyzerFactory;
use Shredio\DoctrineQueries\PhpStan\Rule\ResultArrayKeyRule;

/**
 * @extends RuleTestCase<ResultArrayKeyRule>
 */
final class ResultArrayKeyRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new ResultArrayKeyRule(self::getContainer()->getByType(PhpStanAnalyzerFactory::class));
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/Data/ResultArrayKeyRuleCases.php'], [
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				18,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findIndexedBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				19,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `author.role.id` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'author.role.id !=\' => null], or iterate over yield().',
				20,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findIndexedBy() - the key `author.role.id` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'author.role.id !=\' => null], or iterate over yield().',
				21,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				22,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findPairsBy() - the key `author` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'author !=\' => null], or iterate over yield().',
				23,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				24,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findPairsBy() - the key `type` is returned as Tests\\Entity\\Enum\\ArticleType, but asArray() accepts only integers and strings as keys. Use Shredio\\DoctrineQueries\\Query\\ScalarQueries to get the database values. Iterate over yield() to keep the keys as they are.',
				29,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findIndexedBy() - the key `createdAt` is returned as DateTimeImmutable, but asArray() accepts only integers and strings as keys. Use Shredio\\DoctrineQueries\\Query\\ScalarQueries to get the database values. Iterate over yield() to keep the keys as they are.',
				30,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findPairsBy() - the key `symbol` is returned as Tests\\Doctrine\\Symbol, but asArray() accepts only integers and strings as keys. Use Shredio\\DoctrineQueries\\Query\\ScalarQueries to get the database values. Iterate over yield() to keep the keys as they are.',
				31,
			],
		]);
	}

	public function testAlternativesOfAnyOfDoNotExcludeNullKeys(): void
	{
		$this->analyse([__DIR__ . '/Data/AnyOfResultArrayKeyRuleCases.php'], [
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				19,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findIndexedBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				20,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				21,
			],
		]);
	}

	public function testOnlyCriteriaOfEveryRowExcludeNullKeys(): void
	{
		$this->analyse([__DIR__ . '/Data/CriteriaNarrowingResultArrayKeyRuleCases.php'], [
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				23,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findIndexedBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				24,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				25,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				26,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				27,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				47,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findIndexedBy() - the key `symbol` can be NULL and asArray() throws an exception for such a row. Exclude the rows with the criteria [\'symbol !=\' => null], or iterate over yield().',
				48,
			],
		]);
	}

	/**
	 * @return string[]
	 */
	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/phpstan-rules.neon'];
	}

}
