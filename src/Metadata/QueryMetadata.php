<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Metadata;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use InvalidArgumentException;
use Shredio\DoctrineQueries\Allocator\FieldAliasAllocator;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\FieldSelectType;
use Shredio\DoctrineQueries\Select\FieldToSelect;
use Shredio\DoctrineQueries\Select\QueryType;

final readonly class QueryMetadata
{

	private FieldMetadataFactory $fieldMetadataFactory;

	public string $rootAlias;

	/**
	 * @param ClassMetadataFactory<ClassMetadata<object>> $classMetadataFactory
	 * @param ClassMetadata<object> $metadata
	 */
	public function __construct(
		ClassMetadataFactory $classMetadataFactory,
		private ClassMetadata $metadata,
		public QueryType $queryType,
		private FieldAliasAllocator $aliasAllocator = new FieldAliasAllocator(),
	)
	{
		$this->fieldMetadataFactory = new FieldMetadataFactory($classMetadataFactory, $metadata);
		$this->rootAlias = $this->aliasAllocator->getNextAlias();
	}

	public function getRootAlias(): string
	{
		return $this->rootAlias;
	}

	public function createAliasAllocatorForChild(): FieldAliasAllocator
	{
		return $this->aliasAllocator->createChild();
	}

	/**
	 * @return array<string, non-empty-string> List of joins as [path => alias]
	 */
	public function getJoins(): array
	{
		return $this->aliasAllocator->getAliases();
	}

	public function getPathForField(Field $field): string
	{
		$parent = $field->getParent();
		if ($parent === null) {
			$alias = $this->rootAlias;
		} else {
			$alias = $this->aliasAllocator->getFor($parent);
		}

		return sprintf('%s.%s', $alias, $field->name);
	}

	/**
	 * @throws FieldNotExistsException
	 * @throws InvalidAssociationPathException
	 */
	public function getFieldMetadata(Field $field): FieldMetadata
	{
		return $this->fieldMetadataFactory->create($field);
	}

	public function getSingleIdentifierField(bool $throwIfIdentifierIsComposite = true): string
	{
		if ($throwIfIdentifierIsComposite) {
			return $this->metadata->getSingleIdentifierFieldName();
		}

		return $this->metadata->getIdentifier()[0] ?? throw new InvalidArgumentException(
			sprintf(
				'Entity "%s" does not have an identifier field defined.',
				$this->metadata->name,
			),
		);
	}

	/**
	 * @return list<string>
	 */
	public function getFieldNames(): array
	{
		/** @var list<string> */
		return $this->metadata->getFieldNames();
	}

	/**
	 * @return iterable<int, FieldToSelect>
	 * @throws FieldNotExistsException
	 * @throws InvalidAssociationPathException
	 */
	public function getFieldsToSelect(Field $field, ?string $alias = null): iterable
	{
		$type = $field->getType();
		if ($type === FieldSelectType::Field) {
			$metadata = $this->fieldMetadataFactory->create($field);

			yield new FieldToSelect($metadata, $alias ?? $metadata->field->name);

			return;
		}

		$fieldMetadata = $this->fieldMetadataFactory->createForAllFieldsIn($field->getParent(), $type === FieldSelectType::SelectAllWithRelations);

		foreach ($fieldMetadata as $metadata) {
			yield new FieldToSelect($metadata, $alias . $metadata->field->name);
		}
	}

}
