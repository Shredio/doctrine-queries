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
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineServiceFactory;
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

		$serviceFactory = new PhpStanDoctrineServiceFactory($objectMetadataResolver, $descriptorRegistry);

		return new DoctrineQueriesRule($objectMetadataResolver, $serviceFactory);
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/Data/DoctrineQueriesRuleCases.php'], [
			[
				'The entity class `Article` does not exist or is not managed by Doctrine.',
				23,
			],
			[
				'The entity class `NonExistentEntity` does not exist or is not managed by Doctrine.',
				28,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidField`.',
				39,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidOrderField`.',
				44,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ArrayQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidSelectField`.',
				49,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findSingleColumnValueBy() - entity Tests\Entity\Article does not have a field or association named `$nonExistentField`.',
				60,
			],
			[
				'Argument #2 must be a constant array representing criteria.',
				68,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidField`.',
				77,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidField`.',
				89,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$author_id`.',
				98,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$symbol_id`.',
				98,
			],
			[
				'Call to method Shredio\DoctrineQueries\DoctrineQueries::subQuery() - entity Tests\Entity\Article does not have a field or association named `$authorId`.',
				103,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ArrayQueries::findBy() - entity Tests\Entity\Article has an invalid association in root entity. The field `auth` is not an association.',
				108,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ArrayQueries::findOneBy() - entity Tests\Entity\Article has an invalid association path `author`. The field `rol` is not an association.',
				109,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ArrayQueries::findIndexedBy() - entity Tests\Entity\Article has an invalid association in root entity. The field `auth` is not an association.',
				110,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ArrayQueries::findPairsBy() - entity Tests\Entity\Article has an invalid association path `author`. The field `rol` is not an association.',
				111,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article has an invalid association in root entity. The field `auth` is not an association.',
				113,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findOneBy() - entity Tests\Entity\Article has an invalid association path `author`. The field `rol` is not an association.',
				114,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findIndexedBy() - entity Tests\Entity\Article has an invalid association in root entity. The field `auth` is not an association.',
				115,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findPairsBy() - entity Tests\Entity\Article has an invalid association path `author`. The field `rol` is not an association.',
				116,
			],
			[
				'Argument #3 (orderBy) must be passed as a named argument. Position is not guaranteed.',
				121,
			],
			[
				'Argument #4 (select) must be passed as a named argument. Position is not guaranteed.',
				121,
			],
			[
				'Argument #5 (joinConfig) must be passed as a named argument. Position is not guaranteed.',
				121,
			],
			[
				'Call to method Shredio\DoctrineQueries\DoctrineQueries::existsManyBy() - entity Tests\Entity\Article does not have a field or association named `$invalidField`.',
				126,
			],
			[
				'Call to method Shredio\DoctrineQueries\DoctrineQueries::existsManyBy() - entity Tests\Entity\Article does not have a field or association named `$invalidField`.',
				137,
			],
			[
				'Call to method Shredio\DoctrineQueries\DoctrineQueries::existsManyBy() - entity Tests\Entity\Article does not have a field or association named `$invalidField`.',
				145,
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
