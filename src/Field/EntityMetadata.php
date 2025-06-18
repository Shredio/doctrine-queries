<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Field;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;

/**
 * @template T of object
 */
final class EntityMetadata
{

	/** @var array<class-string, self<object>> */
	private array $factoryCache = [];

	public readonly string $alias;

	/** @var ClassMetadata<T> */
	public readonly ClassMetadata $metadata;

	/**
	 * @param class-string<T> $entity
	 */
	public function __construct(
		private readonly ManagerRegistry $managerRegistry,
		public readonly string $entity,
		private readonly DoctrineAliasAllocator $aliasAllocator = new DoctrineAliasAllocator(),
	)
	{
		$this->alias = $this->aliasAllocator->allocate();
		$this->metadata = $this->getClassMetadata($entity);
	}

	public function createField(FieldPath $fieldPath): MappedFieldPath
	{
		$root = $fieldPath->getRoot();
		$next = $fieldPath->next();
		
		if ($next !== null) {
			// This is a relation
			if (!$this->metadata->hasAssociation($root)) {
				throw new InvalidArgumentException(sprintf('Association "%s" not found in entity "%s"', $root, $this->entity));
			}

			$mapping = $this->metadata->getAssociationMapping($root);

			if (!$mapping instanceof ManyToOneAssociationMapping) {
				throw new InvalidArgumentException(sprintf('Association "%s" in entity "%s" is not a ManyToOne association', $root, $this->entity));
			}

			$targetFactory = $this->getFactoryForEntity($mapping->targetEntity);
			return $targetFactory->createField($next);
		} else {
			// This is the final field
			if (!$this->metadata->hasField($root) && !$this->metadata->hasAssociation($root)) {
				throw new InvalidArgumentException(sprintf('Field "%s" not found in entity "%s"', $root, $this->entity));
			}
			
			return new MappedFieldPath(
				$this->alias,
				$this->entity,
				$root,
				$this->metadata->hasAssociation($root),
			);
		}
	}

	public function getSingleFieldName(): ?string
	{
		$fieldNames = $this->metadata->getFieldNames();
		if (count($fieldNames) === 1) {
			return reset($fieldNames);
		}

		return null;
	}

	/**
	 * @return iterable<int, MappedFieldPath>
	 */
	public function getSelectableFields(bool $withRelations = false): iterable
	{
		foreach ($this->metadata->getFieldNames() as $fieldName) {
			yield new MappedFieldPath(
				$this->alias,
				$this->entity,
				$fieldName,
				false,
			);
		}

		if (!$withRelations) {
			return;
		}

		foreach ($this->metadata->getAssociationNames() as $associationName) {
			if (!$this->metadata->hasAssociation($associationName)) {
				continue;
			}

			$mapping = $this->metadata->getAssociationMapping($associationName);
			if (!$mapping instanceof ManyToOneAssociationMapping) {
				continue; // Only handle ManyToOne associations
			}

			yield new MappedFieldPath(
				$this->alias,
				$this->entity,
				$associationName,
				true,
			);
		}
	}

	/**
	 * @template TEntity of object
	 * @param class-string<TEntity> $entity
	 * @return ClassMetadata<TEntity>
	 */
	private function getClassMetadata(string $entity): ClassMetadata
	{
		$entityManager = $this->managerRegistry->getManagerForClass($entity);

		if ($entityManager === null) {
			throw new InvalidArgumentException(sprintf('No manager found for entity "%s"', $entity));
		}

		/** @var ClassMetadata<TEntity> */
		return $entityManager->getClassMetadata($entity);
	}

	/**
	 * @param class-string $entity
	 * @return self<object>
	 */
	private function getFactoryForEntity(string $entity): self
	{
		if (!isset($this->factoryCache[$entity])) {
			$this->factoryCache[$entity] = new self($this->managerRegistry, $entity, $this->aliasAllocator);
		}

		return $this->factoryCache[$entity];
	}

}
