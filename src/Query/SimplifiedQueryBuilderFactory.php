<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Select\SelectParser;

/**
 * @internal
 */
final readonly class SimplifiedQueryBuilderFactory
{

	public function __construct(
		private ManagerRegistry $managerRegistry,
		private SelectParser $selectParser = new SelectParser(),
	)
	{
	}

	public function withSelectParser(SelectParser $selectParser): self
	{
		return new self($this->managerRegistry, $selectParser);
	}

	public function withRelations(bool $withRelations): self
	{
		return new self($this->managerRegistry, $this->selectParser->withRequireRelations($withRelations));
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @return ClassMetadata<T>
	 */
	public function getMetadata(string $entity): ClassMetadata
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		return $em->getClassMetadata($entity);
	}


	/**
	 * @template T of object
	 * @param class-string<T> $entity
	 * @param array<string, mixed> $criteria
	 * @param array<string, 'ASC'|'DESC'> $orderBy
	 * @param string[] $select
	 */
	public function create(string $entity, array $select = [], array $criteria = [], array $orderBy = [], bool $distinct = false): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $em->getClassMetadata($entity);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, 'a');

		if ($select) {
			$qb->select($this->selectParser->getFromSelect($metadata, $select, 'a'));
		} else {
			$qb->select($this->selectParser->getForAll($metadata, 'a'));
		}

		if ($distinct) {
			$qb->distinct();
		}

		if ($criteria) {
			$this->applyCriteria($qb, $criteria, 'a');
		}

		if ($orderBy) {
			$this->applyOrderBy($qb, $orderBy);
		}

		return $qb;
	}

	/**
	 * @param class-string $entity
	 * @param array<string, mixed> $criteria
	 */
	public function createCount(string $entity, array $criteria = []): QueryBuilder
	{
		$em = $this->managerRegistry->getManagerForClass($entity);
		assert($em instanceof EntityManagerInterface);

		$metadata = $em->getClassMetadata($entity);

		$qb = $em->createQueryBuilder();
		$qb->from($entity, 'a');

		$fieldNames = $metadata->getIdentifierFieldNames();

		if (count($fieldNames) === 1) {
			$qb->select(sprintf('COUNT(a.%s)', $fieldNames[0]));
		} else {
			$qb->select('COUNT(a)');
		}

		$this->applyCriteria($qb, $criteria, 'a');

		return $qb;
	}

	/**
	 * @param array<string, mixed> $criteria
	 */
	private function applyCriteria(QueryBuilder $qb, array $criteria, string $alias): void
	{
		foreach (CriteriaParser::parse($criteria) as $parsed) {
			$qb->andWhere($alias . '.' . $parsed->getExpression());

			if ($parsed->parameterName) {
				$qb->setParameter($parsed->parameterName, $parsed->value);
			}
		}
	}

	/**
	 * @param QueryBuilder $qb
	 * @param array<string, 'ASC'|'DESC'> $orderBy
	 */
	private function applyOrderBy(QueryBuilder $qb, array $orderBy): void
	{
		foreach ($orderBy as $field => $direction) {
			$qb->addOrderBy(sprintf('a.%s', $field), $direction);
		}
	}

}
