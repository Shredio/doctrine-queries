<?php declare(strict_types = 1);

namespace Tests\PhpStan\RuntimeTypes;

use Shredio\DoctrineQueries\DoctrineQueries;
use SortDirection;
use Tests\Entity\Article;
use Tests\Entity\TypedValues;

/**
 * Every method runs one query. RuntimeTypesTest infers the type of each returned expression with PHPStan
 * and checks that the values the database driver really returns fit into it.
 */
final readonly class RuntimeTypesCases
{

	private const array ById = ['id' => SortDirection::Ascending];

	public function __construct(
		private DoctrineQueries $queries,
	)
	{
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsAllFields(): array
	{
		return $this->queries->scalars->findBy(TypedValues::class, orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsAllFieldsYielded(): array
	{
		return iterator_to_array($this->queries->scalars->findBy(TypedValues::class, orderBy: self::ById)->yield(), false);
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsWildcard(): array
	{
		return $this->queries->scalars->findBy(TypedValues::class, orderBy: self::ById, select: ['*'])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsNarrowedByCriteria(): array
	{
		return $this->queries->scalars->findBy(TypedValues::class, [
			'nullableNumber !=' => null,
			'nullableFlag !=' => null,
			'nullablePrice !=' => null,
			'nullableRatio !=' => null,
			'nullableMoment !=' => null,
			'flag' => false,
			'small' => -3,
		])->asArray();
	}

	/**
	 * MySQL compares strings case-insensitively with the default collation, the row with "label" matches too.
	 *
	 * @return mixed[]
	 */
	public function scalarsNarrowedByStringCriteria(): array
	{
		return $this->queries->scalars->findBy(TypedValues::class, ['label' => 'LABEL'], select: ['id', 'label', 'code'])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsNarrowedByDateCriteria(): array
	{
		return $this->queries->scalars->findBy(TypedValues::class, ['day' => '2021-01-02', 'flag' => 1], select: ['id', 'day', 'flag'])->asArray();
	}

	/**
	 * @return mixed[]|null
	 */
	public function scalarsOneBy(): ?array
	{
		return $this->queries->scalars->findOneBy(TypedValues::class, ['id' => 1]);
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsAssociations(): array
	{
		return $this->queries->scalars->findBy(Article::class, orderBy: self::ById, select: ['**'])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsRelatedFieldsLeftJoin(): array
	{
		return $this->queries->scalars->findBy(Article::class, orderBy: self::ById, select: ['id', 'author.**' => 'author_', 'author.role.*' => 'role_'])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsRelatedFieldsInnerJoin(): array
	{
		return $this->queries->scalars->findBy(Article::class, orderBy: self::ById, select: ['id', 'author.role.id' => 'roleId', 'author.role.name' => 'roleName'], joinConfig: 'inner')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsColumnValuesOfBoolean(): array
	{
		return $this->queries->scalars->findColumnValuesBy(TypedValues::class, 'flag', orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsColumnValuesOfDecimal(): array
	{
		return $this->queries->scalars->findColumnValuesBy(TypedValues::class, 'price', orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsColumnValuesOfBigint(): array
	{
		return $this->queries->scalars->findColumnValuesBy(TypedValues::class, 'big', orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsColumnValuesOfNullableFloat(): array
	{
		return $this->queries->scalars->findColumnValuesBy(TypedValues::class, 'nullableRatio', orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsColumnValuesOfIntBackedEnum(): array
	{
		return $this->queries->scalars->findColumnValuesBy(TypedValues::class, 'priority', orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsColumnValuesOfAssociation(): array
	{
		return $this->queries->scalars->findColumnValuesBy(Article::class, 'author', orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsColumnValuesOfLeftJoinedField(): array
	{
		return $this->queries->scalars->findColumnValuesBy(Article::class, 'author.role.id', orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsPairsWithLeftJoinedValue(): array
	{
		return $this->queries->scalars->findPairsBy(Article::class, 'id', 'author.role.name')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsPairs(): array
	{
		return $this->queries->scalars->findPairsBy(TypedValues::class, 'small', 'price')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsIndexed(): array
	{
		return $this->queries->scalars->findIndexedBy(TypedValues::class, 'big', select: ['flag', 'ratio'])->asArray();
	}

	public function scalarsSingleColumnValueOfDecimal(): mixed
	{
		return $this->queries->scalars->findSingleColumnValueBy(TypedValues::class, 'price', ['id' => 1]);
	}

	public function scalarsSingleColumnValueOfBoolean(): mixed
	{
		return $this->queries->scalars->findSingleColumnValueBy(TypedValues::class, 'flag', ['id' => 1]);
	}

	/**
	 * @return mixed[]
	 */
	public function arraysAllFields(): array
	{
		return $this->queries->arrays->findBy(TypedValues::class, orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function arraysNarrowedByCriteria(): array
	{
		return $this->queries->arrays->findBy(TypedValues::class, ['label' => 'LABEL', 'small' => [7, -3]], select: ['id', 'label', 'small'])->asArray();
	}

	/**
	 * Unlike scalars, arrays are converted to PHP values, so a boolean criteria narrows the type.
	 *
	 * @return mixed[]
	 */
	public function arraysNarrowedByBooleanCriteria(): array
	{
		return $this->queries->arrays->findBy(TypedValues::class, ['flag' => false, 'priority' => 1], select: ['id', 'flag', 'priority'])->asArray();
	}

	/**
	 * Aliased fields are converted to PHP values like the not aliased ones.
	 *
	 * @return mixed[]
	 */
	public function arraysAliasedFields(): array
	{
		return $this->queries->arrays->findBy(TypedValues::class, orderBy: self::ById, select: ['moment' => 'at', 'price' => 'cost', 'flag' => 'enabled', 'priority' => 'level', 'nullableMoment' => 'maybeAt'])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function arraysIndexedByAssociation(): array
	{
		return $this->queries->arrays->findIndexedBy(Article::class, 'author', select: ['title'])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function arraysWildcard(): array
	{
		return $this->queries->arrays->findBy(TypedValues::class, orderBy: self::ById, select: ['*'])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function arraysRelatedFields(): array
	{
		return $this->queries->arrays->findBy(Article::class, orderBy: self::ById, select: ['**', 'author.**' => 'author_', 'author.role.*' => 'role_'])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function arraysColumnValuesOfBigint(): array
	{
		return $this->queries->arrays->findColumnValuesBy(TypedValues::class, 'big', orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function arraysColumnValuesOfLeftJoinedField(): array
	{
		return $this->queries->arrays->findColumnValuesBy(Article::class, 'author.role.name', orderBy: self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function arraysPairs(): array
	{
		return $this->queries->arrays->findPairsBy(TypedValues::class, 'id', 'priority')->asArray();
	}

}
