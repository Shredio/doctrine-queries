<?php declare(strict_types = 1);

namespace Tests\PhpStan;

use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\DefaultDescriptorRegistry;
use PHPStan\Type\Doctrine\Descriptors\ArrayType;
use PHPStan\Type\Doctrine\Descriptors\BigIntType;
use PHPStan\Type\Doctrine\Descriptors\BinaryType;
use PHPStan\Type\Doctrine\Descriptors\DateTimeImmutableType;
use PHPStan\Type\Doctrine\Descriptors\DateTimeType;
use PHPStan\Type\Doctrine\Descriptors\DateType;
use PHPStan\Type\Doctrine\Descriptors\DecimalType;
use PHPStan\Type\Doctrine\Descriptors\IntegerType;
use PHPStan\Type\Doctrine\Descriptors\JsonType;
use PHPStan\Type\Doctrine\Descriptors\ReflectionDescriptor;
use PHPStan\Type\Doctrine\Descriptors\SimpleArrayType;
use PHPStan\Type\Doctrine\Descriptors\StringType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use Shredio\DoctrineQueries\PhpStan\PhpStanAnalyzerFactory;
use Shredio\DoctrineQueries\PhpStan\Rule\DoctrineQueriesRule;
use Tests\Doctrine\SymbolType;

final class DoctrineQueriesRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		$objectMetadataResolver = new ObjectMetadataResolver(__DIR__ . '/Data/entity-manager.php', __DIR__ . '/tmp');
		$descriptorRegistry = new DefaultDescriptorRegistry([
			new ArrayType(),
			new BigIntType(),
			new BinaryType(),
			new DateTimeImmutableType(),
			new DateTimeType(),
			new DateType(),
			new DecimalType(new DriverDetector()),
			new JsonType(),
			new IntegerType(),
			new StringType(),
			new SimpleArrayType(),
			new ReflectionDescriptor(SymbolType::class, $this->createReflectionProvider(), self::getContainer()),
		]);

		$analyzerFactory = new PhpStanAnalyzerFactory($objectMetadataResolver, $descriptorRegistry, new DriverDetector());

		return new DoctrineQueriesRule($analyzerFactory);
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/Data/DoctrineQueriesRuleCases.php'], [
			[
				'The entity class `Article` does not exist or is not managed by Doctrine.',
				26,
			],
			[
				'The entity class `NonExistentEntity` does not exist or is not managed by Doctrine.',
				31,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				42,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidOrderField`.',
				47,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidSelectField`.',
				52,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findSingleColumnValueBy() - entity Tests\\Entity\\Article does not have a field or association named `$nonExistentField`.',
				63,
			],
			[
				'Argument #2 must be a constant array representing criteria.',
				71,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				80,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				92,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$author_id`.',
				101,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$symbol_id`.',
				101,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::subQuery() - entity Tests\\Entity\\Article does not have a field or association named `$authorId`.',
				106,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `auth` is not an association.',
				111,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findOneBy() - entity Tests\\Entity\\Article has an invalid association path `author`. The field `rol` is not an association.',
				112,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findIndexedBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `auth` is not an association.',
				113,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findPairsBy() - entity Tests\\Entity\\Article has an invalid association path `author`. The field `rol` is not an association.',
				114,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `auth` is not an association.',
				116,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findOneBy() - entity Tests\\Entity\\Article has an invalid association path `author`. The field `rol` is not an association.',
				117,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findIndexedBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `auth` is not an association.',
				118,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findPairsBy() - entity Tests\\Entity\\Article has an invalid association path `author`. The field `rol` is not an association.',
				119,
			],
			[
				'Argument #3 (orderBy) must be passed as a named argument. Position is not guaranteed.',
				124,
			],
			[
				'Argument #4 (select) must be passed as a named argument. Position is not guaranteed.',
				124,
			],
			[
				'Argument #5 (pagination) must be passed as a named argument. Position is not guaranteed.',
				124,
			],
			[
				'Argument #6 (joinConfig) must be passed as a named argument. Position is not guaranteed.',
				124,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::existsManyBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				129,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::existsManyBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				140,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::existsManyBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				148,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ObjectQueries::findBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `unknownField` is not an association.',
				153,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ObjectQueries::findBy() - entity Tests\\Entity\\Article has an invalid association path `author`. The field `nonExistentAssociation` is not an association.',
				158,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ObjectQueries::findBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `title` is not an association.',
				163,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countBy() - entity Tests\\Entity\\Article does not have a field or association named `$nonExistentField`.',
				240,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `auth` is not an association.',
				241,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ObjectQueries::findBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `auth` is not an association.',
				252,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ObjectQueries::findOneBy() - entity Tests\\Entity\\Article has an invalid association path `author`. The field `rol` is not an association.',
				253,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::existsManyBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				258,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findPairsBy() - entity Tests\\Entity\\Article does not have a field or association named `$unknownKey`.',
				263,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findPairsBy() - entity Tests\\Entity\\Article does not have a field or association named `$unknownValue`.',
				264,
			],
			[
				'Argument #4 (orderBy) must be passed as a named argument. Position is not guaranteed.',
				269,
			],
			[
				'Argument #6 (pagination) must be passed as a named argument. Position is not guaranteed.',
				269,
			],
			[
				'Argument #7 (joinConfig) must be passed as a named argument. Position is not guaranteed.',
				269,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - criteria `title BETWEEN` uses an unsupported operator. Supported operators are: =, !=, <>, <, <=, >, >=, LIKE, NOT LIKE.',
				274,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countBy() - criteria `id IN (1) OR e0.id =` uses an unsupported operator. Supported operators are: =, !=, <>, <, <=, >, >=, LIKE, NOT LIKE.',
				275,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::deleteBy() - criteria `author.name` refers to a related entity, DQL DELETE does not support joins. Filter by the association with a sub-query instead.',
				295,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article has an invalid association path `title`. The field `title` is not an association.',
				306,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article has an invalid association path `author.rol`. The field `rol` is not an association.',
				307,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findColumnValuesBy() - `hashTags` of entity Tests\\Entity\\Article is a to-many association, use a field of the related entity instead, e.g. `hashTags.<field>`.',
				312,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - `hashTags` of entity Tests\\Entity\\Article is a to-many association, use a field of the related entity instead, e.g. `hashTags.<field>`.',
				313,
			],
			[
				'The first argument must be a constant string representing the entity class name e.g. `Account::class`.',
				325,
			],
			[
				'Argument #3 must be a constant array representing orderBy.',
				336,
			],
			[
				'Argument #4 must be a constant array representing select.',
				337,
			],
			[
				'Argument #6 must be a constant array representing joinConfig.',
				338,
			],
			[
				'Argument #4 must be a constant array representing preload.',
				339,
			],
			[
				'Argument #2 must be a constant string representing a field name.',
				344,
			],
			[
				'Argument #2 must be a constant string representing a field name.',
				345,
			],
			[
				'Argument #3 must be a constant string representing a field name.',
				346,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article has an invalid association path `title`. The field `title` is not an association.',
				351,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ObjectQueries::findBy() - entity Tests\\Entity\\Article has an invalid association path `title`. The field `title` is not an association.',
				352,
			],
			[
				'Argument #2 must be a constant array of constant arrays with constant string keys representing criteria.',
				361,
			],
			[
				'Argument #2 must be a constant array of constant arrays with constant string keys representing criteria.',
				362,
			],
			[
				'Argument #2 must be a constant array of constant arrays with the same keys representing criteria. Missing keys: `title`',
				367,
			],
			[
				'Argument #2 must be a constant array of constant arrays with the same keys representing criteria. Unexpected keys: `title`',
				368,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::existsManyBy() - entity Tests\\Entity\\Article does not have a field or association named `$author.name`.',
				373,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findColumnValuesBy() - argument #2 (field) must be a single field, the wildcard `*` selects more of them.',
				386,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findSingleColumnValueBy() - argument #2 (field) must be a single field, the wildcard `author.*` selects more of them.',
				387,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findPairsBy() - argument #2 (key) must be a single field, the wildcard `**` selects more of them.',
				388,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findPairsBy() - argument #3 (value) must be a single field, the wildcard `author.**` selects more of them.',
				389,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findIndexedBy() - argument #2 (indexField) must be a single field, the wildcard `*` selects more of them.',
				390,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countBy() - argument #3 (field) must be a single field, the wildcard `*` selects more of them.',
				391,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::subQuery() - the sub-query selects 2 columns, but the value it is compared with needs exactly one.',
				397,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::subQuery() - the sub-query selects 2 columns, but the value it is compared with needs exactly one.',
				398,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::subQuery() - entity Tests\\Entity\\Author does not have a field or association named `$unknown`.',
				399,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findBy() - alias `t, e0.content AS c` of the selected field `title` is not a valid identifier, use letters, digits and underscores and do not start with a digit.',
				410,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findOneBy() - alias `author id` of the selected field `author.id` is not a valid identifier, use letters, digits and underscores and do not start with a digit.',
				411,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findOneBy() - alias `author name` of the selected field `author.name` is not a valid identifier, use letters, digits and underscores and do not start with a digit.',
				411,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findIndexedBy() - alias `1title` of the selected field `title` is not a valid identifier, use letters, digits and underscores and do not start with a digit.',
				412,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findSingleColumnValueBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidOrderField`.',
				418,
			],
		]);
	}

	public function testAnyOfCriteria(): void
	{
		$this->analyse([__DIR__ . '/Data/AnyOfCriteriaRuleCases.php'], [
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				102,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findOneBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidAlternativeField`.',
				107,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findOneBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidCriteriaField`.',
				107,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				112,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ObjectQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$firstInvalidField`.',
				117,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ObjectQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$secondInvalidField`.',
				117,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$nestedInvalidField`.',
				122,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidFieldOfSecondGroup`.',
				127,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidFieldInVariable`.',
				136,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidRelatedField`.',
				141,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article has an invalid association path `title`. The field `title` is not an association.',
				146,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - `hashTags` of entity Tests\\Entity\\Article is a to-many association, use a field of the related entity instead, e.g. `hashTags.<field>`.',
				151,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - criteria `id BETWEEN` uses an unsupported operator. Supported operators are: =, !=, <>, <, <=, >, >=, LIKE, NOT LIKE.',
				156,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findIndexedBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				161,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ArrayQueries::findPairsBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				162,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findColumnValuesBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				163,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findSingleColumnValueBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				164,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ObjectQueries::findOneBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				165,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				166,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::existsBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				167,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::deleteBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				168,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::subQuery() - entity Tests\\Entity\\Article does not have a field or association named `$invalidField`.',
				169,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::subQuery() - entity Tests\\Entity\\Author does not have a field or association named `$invalidAuthorField`.',
				176,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::deleteBy() - criteria `author.name` refers to a related entity, DQL DELETE does not support joins. Filter by the association with a sub-query instead.',
				184,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::deleteBy() - criteria `hashTags.hashtag` refers to a related entity, DQL DELETE does not support joins. Filter by the association with a sub-query instead.',
				185,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - criteria item 0 has no field name, only Criteria::anyOf() can be passed without one, string given.',
				190,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - criteria item 0 has no field name, only Criteria::anyOf() can be passed without one, array<string, string> given.',
				191,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::deleteBy() - criteria item 5 has no field name, only Criteria::anyOf() can be passed without one, null given.',
				192,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - criteria item 0 has no field name, only Criteria::anyOf() can be passed without one, string given.',
				197,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - criteria `title` is compared with Criteria::anyOf(), pass it among the criteria without a key.',
				202,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - criteria `author.name` is compared with Criteria::anyOf(), pass it among the criteria without a key.',
				203,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - every alternative of Criteria::anyOf() must be a constant array representing criteria.',
				213,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - every alternative of Criteria::anyOf() must be a constant array representing criteria.',
				214,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - every alternative of Criteria::anyOf() must be a constant array representing criteria.',
				215,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - every alternative of Criteria::anyOf() must be a constant array representing criteria.',
				216,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - every alternative of Criteria::anyOf() must be a constant array representing criteria.',
				217,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - an alternative of Criteria::anyOf() can have no criteria, which throws an exception.',
				222,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - an alternative of Criteria::anyOf() can have no criteria, which throws an exception.',
				223,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - Criteria::anyOf() is called without an alternative, which throws an exception.',
				224,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - an alternative of Criteria::anyOf() can have no criteria, which throws an exception.',
				231,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidFieldOfLongerGroup`.',
				248,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidFieldOfConditionalAlternative`.',
				251,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidUnpackedField`.',
				257,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidInlineUnpackedField`.',
				258,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidNamedField`.',
				263,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\Query\\ScalarQueries::findBy() - entity Tests\\Entity\\Article does not have a field or association named `$invalidFieldOfLastAlternative`.',
				268,
			],
		]);
	}

	public function testCountGroupedBy(): void
	{
		$this->analyse([__DIR__ . '/Data/CountGroupedByRuleCases.php'], [
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - entity Tests\\Entity\\Article does not have a field or association named `$nonExistentKey`.',
				23,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - entity Tests\\Entity\\Article has an invalid association path `auth`. The field `auth` is not an association.',
				24,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - argument #2 (groupBy) must be a single field, the wildcard `author.*` selects more of them.',
				25,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - `hashTags` of entity Tests\\Entity\\Article is a to-many association, use a field of the related entity instead, e.g. `hashTags.<field>`.',
				26,
			],
			[
				'Argument #2 must be a constant string representing a field name.',
				27,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - entity Tests\\Entity\\Article does not have a field or association named `$nonExistentNamedKey`.',
				28,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - entity Tests\\Entity\\Article does not have a field or association named `$nonExistentField`.',
				33,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - criteria `id IN (1) OR e0.id =` uses an unsupported operator. Supported operators are: =, !=, <>, <, <=, >, >=, LIKE, NOT LIKE.',
				34,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - entity Tests\\Entity\\Article does not have a field or association named `$nonExistentCountedField`.',
				35,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - argument #4 (field) must be a single field, the wildcard `*` selects more of them.',
				36,
			],
			[
				'Argument #6 (joinConfig) must be passed as a named argument. Position is not guaranteed.',
				37,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::countGroupedBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `auth` is not an association.',
				38,
			],
			[
				'The entity class `NonExistentEntity` does not exist or is not managed by Doctrine.',
				39,
			],
		]);
	}

	public function testAggregations(): void
	{
		$this->analyse([__DIR__ . '/Data/AggregationRuleCases.php'], [
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::sumBy() - the field `label` of entity Tests\\Entity\\TypedValues has the type `string` and cannot be summed, only integer, decimal and float fields can.',
				30,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::sumBy() - the field `day` of entity Tests\\Entity\\TypedValues has the type `date_immutable` and cannot be summed, only integer, decimal and float fields can.',
				31,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::sumGroupedBy() - the field `title` of entity Tests\\Entity\\Article has the type `string` and cannot be summed, only integer, decimal and float fields can.',
				32,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::sumBy() - `author` of entity Tests\\Entity\\Article is an association and cannot be aggregated, use a field of the related entity instead, e.g. `author.<field>`.',
				33,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::minBy() - `author` of entity Tests\\Entity\\Article is an association and cannot be aggregated, use a field of the related entity instead, e.g. `author.<field>`.',
				34,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::maxGroupedBy() - `author.role` of entity Tests\\Entity\\Article is an association and cannot be aggregated, use a field of the related entity instead, e.g. `author.role.<field>`.',
				35,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::maxBy() - `hashTags` of entity Tests\\Entity\\Article is a to-many association, use a field of the related entity instead, e.g. `hashTags.<field>`.',
				36,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::minBy() - entity Tests\\Entity\\Article does not have a field or association named `$nonExistentField`.',
				37,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::sumBy() - entity Tests\\Entity\\Article has an invalid association path `auth`. The field `auth` is not an association.',
				38,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::maxBy() - argument #2 (field) must be a single field, the wildcard `*` selects more of them.',
				39,
			],
			[
				'Argument #2 must be a constant string representing a field name.',
				40,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::sumGroupedBy() - entity Tests\\Entity\\Article does not have a field or association named `$nonExistentKey`.',
				45,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::minGroupedBy() - argument #3 (groupBy) must be a single field, the wildcard `author.*` selects more of them.',
				46,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::maxGroupedBy() - `hashTags` of entity Tests\\Entity\\Article is a to-many association, use a field of the related entity instead, e.g. `hashTags.<field>`.',
				47,
			],
			[
				'Argument #3 must be a constant string representing a field name.',
				48,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::sumBy() - entity Tests\\Entity\\Article does not have a field or association named `$nonExistentCriteria`.',
				49,
			],
			[
				'Argument #4 (joinConfig) must be passed as a named argument. Position is not guaranteed.',
				50,
			],
			[
				'Argument #5 (joinConfig) must be passed as a named argument. Position is not guaranteed.',
				51,
			],
			[
				'Call to method Shredio\\DoctrineQueries\\DoctrineQueries::minBy() - entity Tests\\Entity\\Article has an invalid association in root entity. The field `auth` is not an association.',
				52,
			],
			[
				'The entity class `NonExistentEntity` does not exist or is not managed by Doctrine.',
				53,
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
