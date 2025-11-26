<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Preload;

use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

/**
 * @internal
 */
final readonly class SmartEntityPreloader
{

	public function __construct(
		private EntityPreloader $preloader,
	)
	{
	}

	/**
	 * @param list<object> $objects
	 * @param non-empty-list<string> $fields
	 */
	public function preload(array $objects, array $fields): void
	{
		if ($objects === []) {
			return;
		}

		$plan = $this->createPlan($fields);

		$this->preloadNested($objects, $plan);
	}

	/**
	 * @param non-empty-list<object> $objects
	 * @param non-empty-array<string, array<string, mixed>> $plan mixed == deep nested array
	 */
	private function preloadNested(array $objects, array $plan): void
	{
		foreach ($plan as $association => $subPlan) {
			if ($subPlan === []) {
				$this->preloader->preload($objects, $association);
			} else {
				$preloaded = $this->preloader->preload($objects, $association);
				if ($preloaded !== []) {
					$this->preloadNested($preloaded, $subPlan); // @phpstan-ignore argument.type (phpstan does not handle recursive types)
				}
			}
		}
	}


	/**
	 * @param non-empty-list<string> $fields
	 * @return non-empty-array<string, array<string, array<string, mixed>>> mixed == deep nested array
	 */
	private function createPlan(array $fields): array
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
