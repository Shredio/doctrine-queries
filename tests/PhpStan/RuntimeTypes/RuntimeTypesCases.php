<?php declare(strict_types = 1);

namespace Tests\PhpStan\RuntimeTypes;

use Shredio\DoctrineQueries\Criteria\Criteria;
use Shredio\DoctrineQueries\DoctrineQueries;
use SortDirection;
use Tests\Entity\Article;
use Tests\Entity\TypedValues;
use Tests\Entity\UsageRecord;

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
	 * The first row matches by the second alternative, its nullable columns are NULL and its other columns differ.
	 *
	 * @return mixed[]
	 */
	public function scalarsNotNarrowedByAlternatives(): array
	{
		return $this->queries->scalars->findBy(TypedValues::class, [
			Criteria::anyOf(
				[
					'nullableNumber !=' => null,
					'nullableFlag !=' => null,
					'nullablePrice !=' => null,
					'nullableRatio !=' => null,
					'nullableMoment !=' => null,
					'flag' => false,
					'small' => -3,
				],
				['id' => 1],
			),
		], self::ById)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsNarrowedByCriteriaAroundAlternatives(): array
	{
		return $this->queries->scalars->findBy(TypedValues::class, [
			'nullableNumber !=' => null,
			Criteria::anyOf(['small' => 7], ['flag' => false]),
			'small' => -3,
		], select: ['id', 'nullableNumber', 'small', 'flag'])->asArray();
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
	/**
	 * @return mixed[]
	 */
	public function countGroupedByAssociation(): array
	{
		return $this->queries->countGroupedBy(Article::class, 'author')->asArray();
	}

	/**
	 * The only article of Jane Smith has no symbol, its count is 0.
	 *
	 * @return mixed[]
	 */
	public function countGroupedByRelatedFieldWithCountedField(): array
	{
		return $this->queries->countGroupedBy(Article::class, 'author.name', field: 'symbol')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function countGroupedBySmallint(): array
	{
		return $this->queries->countGroupedBy(TypedValues::class, 'small')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function countGroupedByBigint(): array
	{
		return $this->queries->countGroupedBy(TypedValues::class, 'big', field: 'nullableNumber', distinct: true)->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function countGroupedByDate(): array
	{
		return $this->queries->countGroupedBy(TypedValues::class, 'day')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function countGroupedByEnum(): array
	{
		return $this->queries->countGroupedBy(TypedValues::class, 'kind', ['kind !=' => null])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function countGroupedByYielded(): array
	{
		return iterator_to_array($this->queries->countGroupedBy(TypedValues::class, 'priority')->yield());
	}

	public function sumOfSmallint(): mixed
	{
		return $this->queries->sumBy(TypedValues::class, 'small');
	}

	public function sumOfBigint(): mixed
	{
		return $this->queries->sumBy(TypedValues::class, 'big');
	}

	public function sumOfDecimal(): mixed
	{
		return $this->queries->sumBy(TypedValues::class, 'price');
	}

	public function sumOfFloat(): mixed
	{
		return $this->queries->sumBy(TypedValues::class, 'ratio');
	}

	public function sumOfRelatedField(): mixed
	{
		return $this->queries->sumBy(Article::class, 'author.role.id');
	}

	public function minOfDecimal(): mixed
	{
		return $this->queries->minBy(TypedValues::class, 'price');
	}

	public function maxOfBigint(): mixed
	{
		return $this->queries->maxBy(TypedValues::class, 'big');
	}

	public function maxOfFloat(): mixed
	{
		return $this->queries->maxBy(TypedValues::class, 'ratio');
	}

	public function maxOfBoolean(): mixed
	{
		return $this->queries->maxBy(TypedValues::class, 'flag');
	}

	public function minOfDate(): mixed
	{
		return $this->queries->minBy(TypedValues::class, 'day');
	}

	public function maxOfDateTime(): mixed
	{
		return $this->queries->maxBy(TypedValues::class, 'moment');
	}

	public function minOfTime(): mixed
	{
		return $this->queries->minBy(TypedValues::class, 'time');
	}

	public function maxOfStringEnum(): mixed
	{
		return $this->queries->maxBy(TypedValues::class, 'kind');
	}

	public function minOfIntegerEnum(): mixed
	{
		return $this->queries->minBy(TypedValues::class, 'priority');
	}

	public function maxOfGuid(): mixed
	{
		return $this->queries->maxBy(TypedValues::class, 'uuid');
	}

	public function maxOfCustomType(): mixed
	{
		return $this->queries->maxBy(Article::class, 'symbol');
	}

	/**
	 * @return mixed[]
	 */
	public function sumGroupedByOfDecimal(): array
	{
		return $this->queries->sumGroupedBy(TypedValues::class, 'price', 'kind')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function sumGroupedByOfNullableInteger(): array
	{
		return $this->queries->sumGroupedBy(TypedValues::class, 'nullableNumber', 'id')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function sumGroupedByOfFloat(): array
	{
		return $this->queries->sumGroupedBy(TypedValues::class, 'ratio', 'small')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function minGroupedByOfDateTime(): array
	{
		return $this->queries->minGroupedBy(TypedValues::class, 'nullableMoment', 'big')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function maxGroupedByOfEnum(): array
	{
		return $this->queries->maxGroupedBy(TypedValues::class, 'priority', 'day')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function maxGroupedByAssociation(): array
	{
		return $this->queries->maxGroupedBy(Article::class, 'createdAt', 'author')->asArray();
	}

	public function sumOfSummableType(): mixed
	{
		return $this->queries->sumBy(UsageRecord::class, 'cost');
	}

	/**
	 * @return mixed[]
	 */
	public function sumGroupedByOfSummableType(): array
	{
		return $this->queries->sumGroupedBy(UsageRecord::class, 'cost', 'provider')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function sumGroupedByOfNullableSummableType(): array
	{
		return $this->queries->sumGroupedBy(UsageRecord::class, 'discount', 'provider')->asArray();
	}

	public function sumOfUnsignedInteger(): mixed
	{
		return $this->queries->sumBy(UsageRecord::class, 'tokens');
	}

	public function sumOfUnsignedBigint(): mixed
	{
		return $this->queries->sumBy(UsageRecord::class, 'bytes');
	}

	public function maxOfUnsignedBigint(): mixed
	{
		return $this->queries->maxBy(UsageRecord::class, 'bytes');
	}

	public function minOfUnsignedSmallint(): mixed
	{
		return $this->queries->minBy(UsageRecord::class, 'retries', ['retries !=' => null]);
	}

	/**
	 * @return mixed[]
	 */
	public function sumGroupedByOfUnsignedInteger(): array
	{
		return $this->queries->sumGroupedBy(UsageRecord::class, 'tokens', 'id')->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function maxGroupedByUnsignedAssociation(): array
	{
		return $this->queries->maxGroupedBy(UsageRecord::class, 'tokens', 'previous', ['previous !=' => null])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function scalarsUnsignedFields(): array
	{
		return $this->queries->scalars->findBy(UsageRecord::class, orderBy: self::ById, select: [
			'id',
			'tokens',
			'balance',
			'retries',
			'bytes',
			'priority',
			'fee',
			'previous',
		])->asArray();
	}

	/**
	 * @return mixed[]
	 */
	public function arraysUnsignedFields(): array
	{
		return $this->queries->arrays->findBy(UsageRecord::class, orderBy: self::ById, select: [
			'id',
			'tokens',
			'balance',
			'retries',
			'bytes',
			'priority',
			'fee',
			'previous',
			'cost',
			'discount',
		])->asArray();
	}

}
