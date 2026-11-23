<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Select\Field;

/**
 * Reads the criteria, the selected fields and the other arguments of the queries from their types.
 *
 * @template TType of object
 * @internal
 */
final readonly class QueryArgumentReader
{

	/**
	 * @param TypeSystem<TType> $types
	 */
	public function __construct(
		private TypeSystem $types,
	)
	{
	}

	/**
	 * The most variants of an argument that are followed, an argument with more of them is taken as not known.
	 */
	private const int MaxVariants = 16;

	/**
	 * The first of the strings the type can be, for the validation. See getOnlyString() for the string it certainly is.
	 *
	 * @param TType $type
	 */
	public function getSingleString(object $type): ?string
	{
		return $this->types->getConstantStrings($type)[0] ?? null;
	}

	/**
	 * The string when the type is a single literal string, null otherwise (e.g. 'a'|'b' or string).
	 *
	 * @param TType $type
	 */
	public function getOnlyString(object $type): ?string
	{
		$strings = $this->types->getConstantStrings($type);

		return count($strings) === 1 ? $strings[0] : null;
	}

	/**
	 * Every string the type can be, none when it can be a string that is not known.
	 *
	 * @param TType $type
	 * @return list<string>
	 */
	public function getStrings(object $type): array
	{
		return $this->types->getConstantStrings($type);
	}

	/**
	 * The selections the query can run with, one for each array the argument can be: an optional item is in one
	 * variant and not in another, a field or an alias that is one of several strings is in a variant by each of them.
	 *
	 * @param TType $type
	 * @return list<array<string|int, string>>|null Selected fields, the aliased ones as [field => alias].
	 *                                             Null when the selection is not known, e.g. list<string>
	 */
	public function getSelectVariants(object $type): ?array
	{
		if (!$this->types->isConstantArray($type)) {
			return null;
		}

		$variants = [];

		foreach ($this->types->getConstantArrays($type) as $constantArray) {
			$selections = [[]];

			foreach ($constantArray->items as $item) {
				$strings = $this->types->getConstantStrings($item->valueType);
				if ($strings === []) {
					return null; // a field or an alias that is not known
				}

				$extendedSelections = $item->optional ? $selections : [];

				foreach ($selections as $selection) {
					foreach ($strings as $string) {
						$extendedSelection = $selection;
						if (is_int($item->key)) {
							$extendedSelection[] = $string;
						} else {
							$extendedSelection[$item->key] = $string;
						}

						$extendedSelections[] = $extendedSelection;
					}
				}

				if (count($extendedSelections) > self::MaxVariants) {
					return null;
				}

				$selections = $extendedSelections;
			}

			array_push($variants, ...$selections);
		}

		return count($variants) > self::MaxVariants ? null : $variants;
	}

	/**
	 * The values of the items the array certainly has, e.g. the joins of ['author' => 'inner']. An optional item,
	 * an item of one of several arrays and a value that is not a single literal are left out.
	 *
	 * @param TType $type
	 * @return array<array-key, int|float|string|bool|null>
	 */
	public function getCertainConstantArrayValues(object $type): array
	{
		$constantArrays = $this->types->isConstantArray($type) ? $this->types->getConstantArrays($type) : [];
		if (count($constantArrays) !== 1) {
			return [];
		}

		$values = [];

		foreach ($constantArrays[0]->items as $item) {
			$itemValues = $this->types->getConstantScalarValues($item->valueType);

			if (!$item->optional && count($itemValues) === 1) {
				$values[$item->key] = $itemValues[0];
			}
		}

		return $values;
	}

	/**
	 * The criteria of fields the query runs with, one variant for each array the criteria can be, e.g. two
	 * for array{id: 1}|array{id: 2}. A variant contains only the criteria it certainly has, so every row matches them:
	 * optional items (array{id?: 1}, built by a condition) are left out, and so are the alternatives of Criteria::anyOf(),
	 * a row can match by another alternative.
	 *
	 * @param TType $type
	 * @return list<list<CriteriaItem<TType>>> No variant when the criteria are not known, e.g. array<string, mixed>
	 */
	public function getCriteriaVariants(object $type): array
	{
		if (!$this->types->isConstantArray($type)) {
			return [];
		}

		$variants = [];

		foreach ($this->types->getConstantArrays($type) as $constantArray) {
			$criteria = [];

			foreach ($constantArray->items as $item) {
				if (!$item->optional && is_string($item->key)) {
					$criteria[] = $this->createCriteriaItem($item->key, $item->valueType);
				}
			}

			$variants[] = $criteria;
		}

		return $variants;
	}

	/**
	 * Every criteria of a field the query can run with, for the validation. A row does not have to match them all,
	 * see getCriteriaVariants() for the criteria that narrow the types of the selected fields.
	 *
	 * @param TType $type
	 * @return list<CriteriaItem<TType>>
	 */
	public function getCriteria(object $type): array
	{
		$criteria = [];

		foreach ($this->iterateConstantArrays($type) as $key => $valueType) {
			if (!is_string($key)) {
				continue;
			}

			$criteria[] = $this->createCriteriaItem($key, $valueType);
		}

		return $criteria;
	}

	/**
	 * @param TType $valueType
	 * @return CriteriaItem<TType>
	 */
	private function createCriteriaItem(string $key, object $valueType): CriteriaItem
	{
		[$field, $operator] = CriteriaParser::parseSingleField($key);

		// Unsupported operators are kept as they are, the validation reports them.
		return new CriteriaItem($field, CriteriaParser::normalizeOperator($operator) ?? $operator, $valueType);
	}

	/**
	 * Criteria items without a field name, only the groups created by Criteria::anyOf() are allowed there.
	 *
	 * @param TType $type
	 * @return list<array{int, TType}> Position and type of each item
	 */
	public function getCriteriaItemsWithoutField(object $type): array
	{
		$items = [];

		foreach ($this->iterateConstantArrays($type) as $key => $valueType) {
			if (is_int($key)) {
				$items[] = [$key, $valueType];
			}
		}

		return $items;
	}

	/**
	 * @param TType $type
	 * @return list<Field>
	 */
	public function getOrderByFields(object $type): array
	{
		$fields = [];

		foreach ($this->iterateConstantArrays($type) as $key => $valueType) {
			if (is_string($key)) {
				$fields[] = new Field($key);
			}
		}

		return $fields;
	}

	/**
	 * @param TType $type
	 * @return list<Field>
	 */
	public function getPreloadFields(object $type): array
	{
		$fields = [];

		foreach ($this->iterateConstantArrays($type) as $valueType) {
			foreach ($this->types->getConstantStrings($valueType) as $fieldName) {
				$fields[] = new Field($fieldName);
			}
		}

		return $fields;
	}

	/**
	 * @param TType $type
	 * @return list<Field>
	 */
	public function getSelectFields(object $type): array
	{
		$fields = [];

		foreach ($this->iterateConstantArrays($type) as $key => $valueType) {
			if (is_int($key)) {
				foreach ($this->types->getConstantStrings($valueType) as $fieldName) {
					$fields[] = new Field($fieldName);
				}

				continue;
			}

			foreach ($this->types->getConstantStrings($valueType) as $alias) {
				$fields[] = new Field($key);
			}
		}

		return $fields;
	}

	/**
	 * Every field the query can select, for the validation. See getSelectVariants() for the selections it runs with.
	 *
	 * @param TType $type
	 * @return array<string|int, string> Selected fields, the aliased ones as [field => alias]
	 */
	public function getSelect(object $type): array
	{
		$select = [];

		foreach ($this->iterateConstantArrays($type) as $key => $valueType) {
			if (is_int($key)) {
				foreach ($this->types->getConstantStrings($valueType) as $fieldName) {
					$select[] = $fieldName;
				}

				continue;
			}

			foreach ($this->types->getConstantStrings($valueType) as $alias) {
				$select[$key] = $alias;
			}
		}

		return $select;
	}

	/**
	 * Every value an item of the array can have, for the validation. See getCertainConstantArrayValues().
	 *
	 * @param TType $type
	 * @return array<array-key, int|float|string|bool|null>
	 */
	public function getConstantArrayValues(object $type): array
	{
		$values = [];

		foreach ($this->iterateConstantArrays($type) as $key => $valueType) {
			foreach ($this->types->getConstantScalarValues($valueType) as $value) {
				$values[$key] = $value;
			}
		}

		return $values;
	}

	/**
	 * @param TType $type
	 * @return iterable<int|string, TType> Keys repeat when the type is a union of arrays
	 */
	private function iterateConstantArrays(object $type): iterable
	{
		foreach ($this->types->getConstantArrays($type) as $constantArray) {
			foreach ($constantArray->items as $item) {
				yield $item->key => $item->valueType;
			}
		}
	}

}
