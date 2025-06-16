<?php declare(strict_types = 1);

namespace Tests\Unit;

use Symfony\Component\VarExporter\VarExporter;

trait Helpers
{

	/**
	 * @param mixed[] $array
	 * @return mixed[]
	 */
	private function unsetColumn(array $array, string $column): array
	{
		foreach ($array as $key => $value) {
			if (is_array($value) && array_key_exists($column, $value)) {
				unset($array[$key][$column]);
			}
		}

		return $array;
	}

	/**
	 * @param mixed[] $array
	 * @param list<string> $columns
	 * @return mixed[]
	 */
	private function unsetColumns(array $array, array $columns): array
	{
		foreach ($columns as $column) {
			$array = $this->unsetColumn($array, $column);
		}

		return $array;
	}

	private function export(mixed $value): void
	{
		echo VarExporter::export($value);
	}

}
