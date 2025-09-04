<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Metadata;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Select\Field;

final class FieldMetadataFactory
{

	/** @var array<string, ClassMetadata<object>> */
	private array $metadataCache = [];

	/**
	 * @param ClassMetadataFactory<ClassMetadata<object>> $classMetadataFactory
	 * @param ClassMetadata<object> $metadata
	 */
	public function __construct(
		private readonly ClassMetadataFactory $classMetadataFactory,
		private readonly ClassMetadata $metadata,
	)
	{
	}

	/**
	 * @throws FieldNotExistsException
	 * @throws InvalidAssociationPathException
	 */
	public function create(Field $field): FieldMetadata
	{
		$entityMetadata = $this->getClassMetadata($field->getParent());
		if ($entityMetadata->hasField($field->name)) {
			$isAssociation = false;
		} else if ($entityMetadata->hasAssociation($field->name)) {
			$isAssociation = true;
		} else {
			throw new FieldNotExistsException($field->name, $entityMetadata->name);
		}

		return new FieldMetadata($this, $entityMetadata, $this->classMetadataFactory, $field, $isAssociation);
	}

	/**
	 * @param ClassMetadata<object> $metadata
	 */
	public function createKnownField(ClassMetadata $metadata, Field $field, bool $isAssociation): FieldMetadata
	{
		return new FieldMetadata($this, $metadata, $this->classMetadataFactory, $field, $isAssociation);
	}

	/**
	 * @return iterable<FieldMetadata>
	 * @throws InvalidAssociationPathException
	 */
	public function createForAllFieldsIn(?string $path = null, bool $includeAssociations = false): iterable
	{
		$fieldPrefix = $path !== null ? $path . '.' : '';

		$metadata = $this->getClassMetadata($path);
		foreach ($metadata->getFieldNames() as $fieldName) {
			yield $this->createKnownField($metadata, new Field($fieldPrefix . $fieldName), false);
		}

		if (!$includeAssociations) {
			return;
		}

		foreach ($metadata->getAssociationMappings() as $mapping) {
			if (!$mapping instanceof ManyToOneAssociationMapping) {
				continue;
			}

			yield $this->createKnownField($metadata, new Field($fieldPrefix . $mapping->fieldName), true);
		}
	}

	/**
	 * @internal
	 * @return ClassMetadata<object>
	 *
	 * @throws InvalidAssociationPathException
	 */
	private function getClassMetadata(?string $parent): ClassMetadata
	{
		if ($parent === null) {
			return $this->metadata;
		}

		if (!isset($this->metadataCache[$parent])) {
			$tempParent = $parent;
			// Walk up the parent chain to find the nearest cached metadata
			while (($pos = strrpos($tempParent, '.')) !== false) {
				$tempParent = substr($tempParent, 0, $pos);
				if (isset($this->metadataCache[$tempParent])) {
					break;
				}
			}

			if ($pos !== false) {
				$metadata = $this->metadataCache[$tempParent];
				$parent = substr($parent, $pos + 1);
			} else {
				$metadata = $this->metadata;
			}

			$parts = explode('.', $parent);

			foreach ($parts as $part) {
				if (!$metadata->hasAssociation($part)) {
					throw new InvalidAssociationPathException($metadata->getName(), $parent, $part);
				}

				$metadata = $this->classMetadataFactory->getMetadataFor(
					$metadata->getAssociationMapping($part)->targetEntity,
				);

				$this->metadataCache[$parent] = $metadata;
			}
		}

		return $this->metadataCache[$parent];
	}

}
