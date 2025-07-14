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
				22,
			],
			[
				'The entity class `NonExistentEntity` does not exist or is not managed by Doctrine.',
				27,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidField`.',
				38,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidOrderField`.',
				43,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ArrayQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidSelectField`.',
				48,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findSingleColumnValueBy() - entity Tests\Entity\Article does not have a field or association named `$nonExistentField`.',
				59,
			],
			[
				'Argument #2 must be a constant array representing criteria.',
				67,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidField`.',
				76,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$invalidField`.',
				88,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$author_id`.',
				97,
			],
			[
				'Call to method Shredio\DoctrineQueries\Query\ScalarQueries::findBy() - entity Tests\Entity\Article does not have a field or association named `$symbol_id`.',
				97,
			],
			[
				'Call to method Shredio\DoctrineQueries\DoctrineQueries::subQuery() - entity Tests\Entity\Article does not have a field or association named `$authorId`.',
				102,
			]
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
