<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Join\JoinParser;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;
use Shredio\DoctrineQueries\Select\SelectParser;

/**
 * A call of a method of ScalarQueries or ArrayQueries whose result type is resolved.
 *
 * @template TType of object
 * @internal
 */
final readonly class QueryCallContext
{

	/**
	 * @param CallArguments<TType> $arguments
	 * @param array<int, string> $parameters The parameters of the method, see QueryMethods::Parameters
	 * @param TypeSystem<TType> $types
	 * @param QueryArgumentReader<TType> $reader
	 * @param FieldTypeResolver<TType> $fieldTypeResolver
	 */
	public function __construct(
		private CallArguments $arguments,
		private array $parameters,
		private QueryMetadata $queryMetadata,
		private QueryType $queryType,
		private TypeSystem $types,
		private QueryArgumentReader $reader,
		private FieldTypeResolver $fieldTypeResolver,
	)
	{
	}

	/**
	 * @param mixed[] $joinConfig
	 * @return array<string, 'left'|'inner'>
	 */
	public static function normalizeJoinConfig(array $joinConfig): array
	{
		$parsedJoinConfig = [];

		foreach ($joinConfig as $key => $value) {
			if (!is_string($key) || ($value !== 'left' && $value !== 'inner')) {
				continue;
			}

			$parsedJoinConfig[$key] = $value;
		}

		return JoinParser::parse($parsedJoinConfig);
	}

	/**
	 * The criteria that narrow the types of the fields, see QueryArgumentReader::getCriteriaVariants().
	 *
	 * @return list<list<CriteriaItem<TType>>>
	 */
	public function getCriteriaVariants(): array
	{
		$type = $this->getArgumentType('criteria');

		return $type === null ? [] : $this->reader->getCriteriaVariants($type);
	}

	/**
	 * The fields passed as constant strings in the argument, more of them when it is one of several strings.
	 *
	 * @return list<string> None when the field is not known
	 */
	public function getFieldArgumentNames(string $parameterName): array
	{
		$type = $this->getArgumentType($parameterName);

		return $type === null ? [] : $this->reader->getStrings($type);
	}

	/**
	 * The type of the field passed as a constant string in the argument, of any of the fields when it is one
	 * of several strings. Null when the field is not known.
	 *
	 * @param list<list<CriteriaItem<TType>>> $criteriaVariants
	 * @return TType|null
	 */
	public function getFieldArgumentType(string $parameterName, array $criteriaVariants): ?object
	{
		$fieldTypes = [];

		foreach ($this->getFieldArgumentNames($parameterName) as $field) {
			$fieldType = $this->getFieldType($field, $criteriaVariants);
			if ($fieldType === null) {
				return null;
			}

			$fieldTypes[] = $fieldType;
		}

		return $fieldTypes === [] ? null : $this->types->union(...$fieldTypes);
	}

	/**
	 * @param list<list<CriteriaItem<TType>>> $criteriaVariants
	 * @return TType|null Null when the field does not exist
	 */
	public function getFieldType(string $field, array $criteriaVariants): ?object
	{
		try {
			$fieldMetadata = $this->queryMetadata->getFieldMetadata(new Field($field));
		} catch (FieldNotExistsException|InvalidAssociationPathException) {
			return null; // reported by the validation
		}

		return $this->fieldTypeResolver->resolve(
			$fieldMetadata,
			$this->queryType,
			$criteriaVariants,
			$this->isInnerJoin($this->getJoinConfig(), $fieldMetadata->field),
		);
	}

	/**
	 * The type of a row with the selected fields, of any of the selections when the query can run with several.
	 *
	 * @param list<list<CriteriaItem<TType>>> $criteriaVariants
	 * @return TType|null Null when the selected fields are not known
	 */
	public function getSelectType(array $criteriaVariants): ?object
	{
		$selectType = $this->getArgumentType('select');
		$selectVariants = $selectType === null ? [[]] : $this->reader->getSelectVariants($selectType);
		if ($selectVariants === null || $selectVariants === []) {
			return null;
		}

		$joinConfig = $this->getJoinConfig();
		$rowTypes = [];

		foreach ($selectVariants as $select) {
			$errors = []; // collects invalid fields instead of throwing, the validation reports them
			$valueTypes = [];
			foreach (SelectParser::getMetadataForSelection($this->queryMetadata, $select, $errors) as $fieldToSelect) {
				$valueTypes[$fieldToSelect->alias] = $this->fieldTypeResolver->resolve(
					$fieldToSelect->metadata,
					$this->queryType,
					$criteriaVariants,
					$this->isInnerJoin($joinConfig, $fieldToSelect->metadata->field),
				);
			}

			$rowTypes[] = $this->types->constantArray($valueTypes);
		}

		return $this->types->union(...$rowTypes);
	}

	/**
	 * @return TType|null
	 */
	private function getArgumentType(string $parameterName): ?object
	{
		$position = array_search($parameterName, $this->parameters, true);

		return $position === false ? null : $this->arguments->getType($position, $parameterName);
	}

	/**
	 * The relations are left joined when the method has no join configuration. A join that is not certainly an inner one
	 * (an optional item, one of several configurations) is taken as a left join, its fields can be NULL.
	 *
	 * @return array<string, 'left'|'inner'>|'left'|'inner'
	 */
	private function getJoinConfig(): array|string
	{
		$type = $this->getArgumentType('joinConfig');
		if ($type === null) {
			return 'left';
		}

		$joinType = $this->reader->getOnlyString($type);
		if ($joinType === 'left' || $joinType === 'inner') {
			return $joinType;
		}

		$joinConfig = $this->reader->getCertainConstantArrayValues($type);
		if ($joinConfig === []) {
			return 'left';
		}

		return self::normalizeJoinConfig($joinConfig);
	}

	/**
	 * @param array<string, 'left'|'inner'>|'left'|'inner' $joinConfig
	 */
	private function isInnerJoin(array|string $joinConfig, Field $field): ?bool
	{
		$parent = $field->getParent();
		if ($parent === null) {
			return null;
		}

		if (is_string($joinConfig)) {
			return $joinConfig === 'inner';
		}

		if (isset($joinConfig[$parent])) {
			return $joinConfig[$parent] === 'inner';
		}

		return false;
	}

}
