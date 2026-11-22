<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan\TypeExtension;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\UnsupportedFieldTypeException;
use Shredio\DoctrineQueries\Join\JoinParser;
use Shredio\DoctrineQueries\Metadata\FieldMetadata;
use Shredio\DoctrineQueries\Metadata\QueryMetadata;
use Shredio\DoctrineQueries\PhpStan\CriteriaItemType;
use Shredio\DoctrineQueries\PhpStan\PhpStanDoctrineService;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;
use Shredio\DoctrineQueries\Select\SelectParser;

final readonly class DynamicReturnTypeContext
{

	private QueryMetadata $queryMetadata;

	/**
	 * @param non-empty-array<Arg> $args
	 * @param class-string $entityClassName
	 */
	public function __construct(
		public array $args,
		public Scope $scope,
		public string $entityClassName,
		public QueryType $queryType,
		private PhpStanDoctrineService $service,
	)
	{
		$this->queryMetadata = $this->service->getQueryMetadataFor($this->entityClassName, $this->queryType);
	}

	/**
	 * @return list<CriteriaItemType>
	 */
	public function getCriteria(int $argumentIndex): array
	{
		$arg = $this->args[$argumentIndex] ?? null;

		if ($arg === null) {
			return [];
		}

		return iterator_to_array($this->service->getCriteriaFromType($this->scope->getType($arg->value)), false);
	}

	/**
	 * @param int $argumentIndex
	 * @param list<CriteriaItemType> $criteria
	 * @param int|null $joinConfigArgumentIndex The relations are left joined when the method has no join configuration
	 */
	public function tryToCreateTypeFromConstantType(
		int $argumentIndex,
		array $criteria = [],
		?int $joinConfigArgumentIndex = null,
	): Type
	{
		$arg = $this->args[$argumentIndex] ?? null;
		if ($arg === null) {
			return new MixedType();
		}

		$field = $this->service->tryGetSingleStringFromType($this->scope->getType($arg->value));
		if ($field === null) {
			return new MixedType();
		}

		try {
			$fieldMetadata = $this->queryMetadata->getFieldMetadata(new Field($field));
		} catch (FieldNotExistsException|InvalidAssociationPathException) {
			return new MixedType(); // reported by DoctrineQueriesRule
		}

		return $this->createNarrowedTypeFor(
			$fieldMetadata,
			$criteria,
			$this->isInnerJoin($this->getJoinConfig($joinConfigArgumentIndex), $fieldMetadata->field),
		);
	}

	/**
	 * @param list<CriteriaItemType> $criteria
	 */
	public function getSelectType(int $argumentIndex, array $criteria = [], ?int $joinConfigArgumentIndex = null): ConstantArrayType
	{
		$select = $this->getSelectFields($this->args[$argumentIndex] ?? null);
		$joinConfig = $this->getJoinConfig($joinConfigArgumentIndex);

		$keyTypes = [];
		$valueTypes = [];

		$errors = []; // collects invalid fields instead of throwing, DoctrineQueriesRule reports them
		$selection = SelectParser::getMetadataForSelection($this->queryMetadata, $select, $errors);

		foreach ($selection as $fieldToSelect) {
			$keyTypes[] = new ConstantStringType($fieldToSelect->alias);
			$valueTypes[] = $this->createNarrowedTypeFor(
				$fieldToSelect->metadata,
				$criteria,
				$this->isInnerJoin($joinConfig, $fieldToSelect->metadata->field),
			);
		}

		return new ConstantArrayType($keyTypes, $valueTypes);
	}

	/**
	 * @return array<string|int, string>
	 */
	private function getSelectFields(?Arg $arg): array
	{
		if ($arg === null) {
			return [];
		}

		return $this->service->getSelectFromType($this->scope->getType($arg->value));
	}

	/**
	 * @param list<CriteriaItemType> $criteria
	 */
	private function createNarrowedTypeFor(FieldMetadata $fieldMetadata, array $criteria, ?bool $isInnerJoin = null): Type
	{
		try {
			$fieldType = $fieldMetadata->getFieldType($isInnerJoin);
		} catch (UnsupportedFieldTypeException) {
			return new MixedType(); // e.g. a to-many association, reported by DoctrineQueriesRule
		}

		// Doctrine converts selected fields to PHP values except in scalar queries, but never IDENTITY() of an association.
		$isDatabaseValue = $this->queryType === QueryType::Scalar || $fieldMetadata->isAssociation;

		return $this->service->determineTypeByFieldCriteria(
			$this->service->createTypeForFieldMapping($fieldType, $isDatabaseValue),
			$fieldMetadata->field->selector, // the selector, the name alone does not tell "id" from "author.id"
			$criteria,
			$this->service->comparesStringsExactly($fieldType),
		);
	}

	/**
	 * @return array<string, 'left'|'inner'>|'left'|'inner'
	 */
	private function getJoinConfig(?int $joinConfigArgumentIndex): array|string
	{
		if ($joinConfigArgumentIndex === null) {
			return 'left';
		}

		$arg = $this->args[$joinConfigArgumentIndex] ?? null;
		if ($arg === null) {
			return 'left';
		}

		$argType = $this->scope->getType($arg->value);

		$type = $this->service->tryGetSingleStringFromType($argType);
		if ($type === 'left' || $type === 'inner') {
			return $type;
		}

		$joinConfig = $this->service->getArrayFromConstantArray($this->scope->getType($arg->value));

		if ($joinConfig === []) {
			return 'left';
		}

		return self::fixJoinConfig($joinConfig);
	}

	/**
	 * @param mixed[] $joinConfig
	 * @return array<string, 'left'|'inner'>
	 */
	public static function fixJoinConfig(array $joinConfig): array
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
