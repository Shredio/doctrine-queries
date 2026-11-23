<?php declare(strict_types = 1);

namespace Tests\PhpStan\RuntimeTypes;

use DateTimeImmutable;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPStan\Type\ConstantTypeHelper;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Context\DoctrineContext;
use Tests\Entity\Enum\ArticleType;
use Tests\Entity\Enum\Priority;
use Tests\Entity\TypedValues;
use Tests\Unit\Helpers;

/**
 * Checks that the result types inferred by the PHPStan extension match the values the database driver returns.
 *
 * Scalar results are not converted by Doctrine, so their PHP types depend on the driver and its options. The test runs
 * against in-memory SQLite by default, set DATABASE_URL to run it against another database (see compose.yaml).
 * The PHPStan analysis uses the same connection, so the inferred types are the ones for that driver.
 */
#[Group(self::Group)]
final class RuntimeTypesTest extends TypeInferenceTestCase
{

	use DoctrineContext;
	use Helpers;

	/** Excluded from mutation testing, see infection.json5 */
	public const string Group = 'runtime-types';

	/**
	 * @return iterable<string, array{string, Type}>
	 */
	public static function provideInferredTypes(): iterable
	{
		$inferredTypes = [];

		self::processFile(__DIR__ . '/RuntimeTypesCases.php', static function (Node $node, Scope $scope) use (&$inferredTypes): void {
			$methodName = $scope->getFunctionName();
			if ($node instanceof Node\Stmt\Return_ && $node->expr !== null && $methodName !== null) {
				$inferredTypes[$methodName] = $scope->getType($node->expr);
			}
		});

		foreach ($inferredTypes as $methodName => $inferredType) {
			yield $methodName => [$methodName, $inferredType];
		}
	}

	#[DataProvider('provideInferredTypes')]
	public function testRuntimeValuesMatchInferredType(string $methodName, Type $inferredType): void
	{
		$this->persistFixtures();
		$this->persistTypedValues();
		$this->persistUsageRecords();

		$value = (new RuntimeTypesCases($this->createDoctrineQueries()))->{$methodName}();

		$this->assertNotEmpty($value, 'The query has to return something, otherwise there is nothing to compare.');

		$mismatches = [];
		self::collectMismatches($inferredType, $value, 'result', $mismatches);

		$this->assertSame([], $mismatches, sprintf(
			"Driver %s returned values PHPStan does not expect.\nInferred type: %s",
			$this->getEntityManager()->getConnection()->getParams()['driver'] ?? 'unknown',
			$inferredType->describe(VerbosityLevel::precise()),
		));
	}

	/**
	 * @return string[]
	 */
	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/runtime-types.neon'];
	}

	/**
	 * Walks the value and reports the deepest places where it does not fit into the inferred type,
	 * so a failure names the column instead of the whole result.
	 *
	 * @param list<string> $mismatches
	 */
	private static function collectMismatches(Type $inferredType, mixed $value, string $path, array &$mismatches): void
	{
		if ($inferredType instanceof MixedType) {
			$mismatches[] = sprintf('%s: inferred as mixed, the extension should know the type of %s', $path, get_debug_type($value));

			return;
		}

		if ($inferredType->isSuperTypeOf(ConstantTypeHelper::getTypeFromValue($value))->yes()) {
			return;
		}

		if (is_array($value)) {
			$mismatchCount = count($mismatches);

			foreach ($value as $key => $item) {
				$keyType = ConstantTypeHelper::getTypeFromValue($key);
				if (!$inferredType->getIterableKeyType()->isSuperTypeOf($keyType)->yes()) {
					$mismatches[] = sprintf('%s: key %s does not fit %s', $path, var_export($key, true), $inferredType->getIterableKeyType()->describe(VerbosityLevel::precise()));

					continue;
				}

				self::collectMismatches($inferredType->getOffsetValueType($keyType), $item, sprintf('%s[%s]', $path, var_export($key, true)), $mismatches);
			}

			if ($mismatchCount === count($mismatches)) {
				$mismatches[] = sprintf('%s: keys %s do not match %s', $path, implode(', ', array_keys($value)), self::describeKeys($inferredType));
			}

			return;
		}

		$mismatches[] = sprintf(
			'%s: inferred %s, returned %s %s',
			$path,
			$inferredType->describe(VerbosityLevel::precise()),
			get_debug_type($value),
			is_scalar($value) ? var_export($value, true) : '',
		);
	}

	private static function describeKeys(Type $type): string
	{
		$keys = [];
		foreach ($type->getConstantArrays() as $constantArray) {
			foreach ($constantArray->getKeyTypes() as $keyType) {
				$keys[] = (string) $keyType->getValue();
			}
		}

		return $keys !== [] ? sprintf('the inferred keys %s', implode(', ', array_unique($keys))) : $type->describe(VerbosityLevel::precise());
	}

	private function persistUsageRecords(): void
	{
		$em = $this->getEntityManager();
		$em->persist($first = self::createUsageRecord(1, 'a', 10, 1.5, discount: 0.5, retries: 2));
		$em->persist(self::createUsageRecord(2, 'b', 0, 0.25, previous: $first));
		$em->flush();
		$em->clear();
	}

	private function persistTypedValues(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new TypedValues(
			id: 1,
			flag: true,
			small: 7,
			big: '9007199254740993', // above 2^53, a float would lose precision
			price: '12.50',
			ratio: 0.5,
			label: 'label',
			// The second row has the same label and code in a different case.
			note: 'note',
			code: 'ABC',
			uuid: '0b6f6b2e-4a6b-4bfa-9d39-7a4b9b9c1d2e',
			day: new DateTimeImmutable('2021-01-02'),
			time: new DateTimeImmutable('03:04:05'),
			moment: new DateTimeImmutable('2021-01-02 03:04:05'),
			payload: ['key' => 1],
			tags: ['first', 'second'],
			kind: ArticleType::News,
			priority: Priority::High,
		));
		$em->persist(new TypedValues(
			id: 2,
			flag: false,
			small: -3,
			big: '0',
			price: '10.00',
			ratio: 2.0,
			label: 'LABEL',
			note: '',
			code: 'abc',
			uuid: '00000000-0000-0000-0000-000000000000',
			day: new DateTimeImmutable('1999-12-31'),
			time: new DateTimeImmutable('00:00:00'),
			moment: new DateTimeImmutable('1999-12-31 23:59:59'),
			payload: [],
			tags: ['only'],
			kind: ArticleType::Blog,
			priority: Priority::Low,
			nullableFlag: false,
			nullableNumber: 0,
			nullablePrice: '1.25',
			nullableRatio: 1.5,
			nullableMoment: new DateTimeImmutable('2000-01-01 00:00:00'),
		));
		$em->flush();
		$em->clear();
	}

}
