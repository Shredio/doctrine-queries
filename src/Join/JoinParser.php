<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Join;

final readonly class JoinParser
{

	/**
	 * @param array<string, 'inner'|'left'> $joinConfig
	 * @return array<string, 'inner'|'left'>
	 */
	public static function parse(array $joinConfig): array
	{
		foreach ($joinConfig as $path => $type) {
			if ($type === 'inner') {
				foreach (self::chopParents($path) as $parent) {
					if (!isset($joinConfig[$parent]) || $joinConfig[$parent] === 'left') {
						$joinConfig[$parent] = 'inner';
					}
				}
			}
		}

		return $joinConfig;
	}

	/**
	 * @return iterable<int, string> Yields parent paths from closest to furthest
	 */
	private static function chopParents(string $parent): iterable
	{
		$pos = strpos($parent, '.');
		while ($pos !== false) {
			yield substr($parent, 0, $pos);

			$pos = strpos($parent, '.', $pos + 1);
		}

		yield $parent;
	}

}
