<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Preload;

use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

/**
 * @internal
 */
final readonly class SmartEntityPreloader
{

	/**
	 * @param list<object> $objects
	 * @param non-empty-list<string> $fields
	 */
	public static function preload(EntityManagerInterface $em, array $objects, array $fields): void
	{
		if (!class_exists(EntityPreloader::class)) {
			throw new \LogicException('EntityPreloader class not found. Make sure the "shipmonk/doctrine-entity-preloader" package is installed.');
		}

		if ($objects === []) {
			return;
		}

		self::preloadNested(new EntityPreloader($em), $objects, self::createPlan($fields));
	}

	/**
	 * @param non-empty-list<object> $objects
	 * @param non-empty-array<string, array<string, mixed>> $plan mixed == deep nested array
	 */
	private static function preloadNested(EntityPreloader $preloader, array $objects, array $plan): void
	{
		foreach ($plan as $association => $subPlan) {
			// EntityPreloader wants a literal-string so that no raw input reaches DQL. The names come from a preload list
			// DoctrineQueriesRule requires to be a constant array of mapped associations, split by explode(), which PHPStan
			// types as a plain string; EntityPreloader still resolves each name through the class metadata.
			$preloaded = $preloader->preload($objects, $association); // @phpstan-ignore argument.type (literal association names split by explode)
			if ($subPlan !== [] && $preloaded !== []) {
				self::preloadNested($preloader, $preloaded, $subPlan); // @phpstan-ignore argument.type (phpstan does not handle recursive types)
			}
		}
	}


	/**
	 * @param non-empty-list<string> $fields
	 * @return non-empty-array<string, array<string, array<string, mixed>>> mixed == deep nested array
	 */
	private static function createPlan(array $fields): array
	{
		$plan = [];
		foreach ($fields as $field) {
			$path = explode('.', $field);
			$current = &$plan;
			foreach ($path as $part) {
				if (!isset($current[$part])) { // @phpstan-ignore isset.offset (phpstan does not handle recursive types)
					$current[$part] = [];
				}
				$current = &$current[$part];
			}
		}

		return $plan; // @phpstan-ignore return.type (phpstan does not handle recursive types)
	}

}
