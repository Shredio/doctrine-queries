<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\PhpStan;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;

/**
 * @api
 * @template T of object
 */
final readonly class PhpStanDoctrineEntityService
{

	/**
	 * @param ClassMetadata<T> $metadata
	 */
	public function __construct(
		private DescriptorRegistry $descriptorRegistry,
		private PhpStanDoctrineEntityServiceFactory $entityServiceFactory,
		public ClassMetadata $metadata,
	)
	{
	}

	public function hasFieldOrAssociation(string $fieldName): bool
	{
		return $this->metadata->hasField($fieldName) || $this->metadata->hasAssociation($fieldName);
	}

	/**
	 * @return list<string>
	 */
	public function getSelectionForAllFields(bool $withRelations = true): array
	{
		$select = [];

		foreach ($this->metadata->getFieldNames() as $fieldName) {
			$select[] = $fieldName;
		}

		if ($withRelations) {
			foreach ($this->metadata->getAssociationMappings() as $mapping) {
				if ($mapping instanceof ManyToOneAssociationMapping) {
					$select[] = $mapping->fieldName;
				}
			}
		}

		return $select;
	}

	public function createTypeForField(string $field, bool $returnScalarType = false): ?Type
	{
		if ($this->metadata->hasField($field)) {
			$mapping = $this->metadata->getFieldMapping($field);

			return $this->resolveDoctrineType(
				$mapping->type,
				$mapping->enumType,
				$mapping->nullable ?? false,
				$returnScalarType,
			);
		}

		if ($this->metadata->hasAssociation($field)) {
			$mapping = $this->metadata->getAssociationMapping($field);

			if (!$mapping instanceof ManyToOneAssociationMapping) {
				return null;
			}

			$entityService = $this->entityServiceFactory->create($mapping->targetEntity);

			$assocFieldName = $entityService->metadata->getFieldForColumn($this->metadata->getSingleAssociationReferencedJoinColumnName($field));

			return $entityService->createTypeForField($assocFieldName, $returnScalarType);
		}

		return null;
	}

	/**
	 * @see https://github.com/phpstan/phpstan-doctrine/blob/2.0.x/src/Type/Doctrine/Query/QueryResultTypeWalker.php#L2026
	 */
	private function resolveDoctrineType(
		string $typeName,
		?string $enumType = null,
		bool $nullable = false,
		bool $returnScalarType = false,
	): Type
	{
		try {
			$descriptor = $this->descriptorRegistry->get($typeName); // @phpstan-ignore phpstanApi.method

			if ($returnScalarType) {
				$type = $descriptor->getDatabaseInternalType();
			} else {
				$type = $descriptor->getWritableToPropertyType();
			}

			if ($enumType !== null) {
				if ($type->isArray()->no()) {
					$type = new ObjectType($enumType);
				} else {
					$type = TypeCombinator::intersect(new ArrayType(
						$type->getIterableKeyType(),
						new ObjectType($enumType),
					), ...TypeUtils::getAccessoryTypes($type));
				}
			}
			if ($type instanceof NeverType) {
				$type = new MixedType();
			}
		} catch (DescriptorNotRegisteredException $e) {
			if ($enumType !== null) {
				$type = new ObjectType($enumType);
			} else {
				$type = new MixedType();
			}
		}

		if ($nullable) {
			$type = TypeCombinator::addNull($type);
		}

		return $type;
	}

}
