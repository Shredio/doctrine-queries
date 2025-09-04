<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Metadata;

use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Shredio\DoctrineQueries\Select\Field;

/**
 * @internal
 */
final readonly class FieldMetadata
{


	/**
	 * @param ClassMetadata<object> $entityMetadata
	 * @param ClassMetadataFactory<ClassMetadata<object>> $classMetadataFactory
	 */
	public function __construct(
		private FieldMetadataFactory $fieldMetadataFactory,
		private ClassMetadata $entityMetadata,
		private ClassMetadataFactory $classMetadataFactory,
		public Field $field,
		public bool $isAssociation,
	)
	{
	}

	/**
	 * @internal phpstan
	 */
	public function getFieldType(?bool $isInnerJoin = null): FieldType
	{
		if (!$this->isAssociation) {
			$fieldMapping = $this->entityMetadata->getFieldMapping($this->field->name);
			$nullable = $fieldMapping->nullable ?? false;
			$parent = $this->field->getParent();

			if ($parent !== null) {
				if ($nullable) {
					$nullable = !($isInnerJoin === true);
				} else if ($isInnerJoin === false) { // left join
					// check if join column is nullable
					// (if join column is nullable, the field is nullable too)
					$parentFieldMetadata = $this->fieldMetadataFactory->create(new Field($parent));
					if ($parentFieldMetadata->getFieldType($isInnerJoin)->nullable) {
						$nullable = true;
					}
				}
			}

			return new FieldType(
				$fieldMapping->type,
				$nullable,
				$fieldMapping->enumType,
				$this->field->hasParent(),
			);
		}

		$mapping = $this->entityMetadata->getAssociationMapping($this->field->name);
		$nullable = $this->isAssociationNullable($mapping);

		$assocMetadata = $this->classMetadataFactory->getMetadataFor($mapping->targetEntity);
		$assocFieldName = $assocMetadata->getFieldForColumn($this->entityMetadata->getSingleAssociationReferencedJoinColumnName($this->field->name));

		$fieldMapping = $assocMetadata->getFieldMapping($assocFieldName);

		return new FieldType(
			$fieldMapping->type,
			$nullable,
			$fieldMapping->enumType,
			true,
			true,
		);
	}

	private function isAssociationNullable(AssociationMapping $mapping): bool
	{
		if ($mapping instanceof ManyToOneAssociationMapping) {
			if (count($mapping->joinColumns) !== 1) {
				throw new \LogicException('Composite foreign keys are not supported.');
			}

			return $mapping->joinColumns[0]->nullable ?? true;

		}

		throw new \LogicException('Only ManyToOne associations are supported.');
	}

}
