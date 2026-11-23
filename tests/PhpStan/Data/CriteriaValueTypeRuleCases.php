<?php declare(strict_types = 1);

namespace Tests\PhpStan\Data;

use DateTime;
use DateTimeImmutable;
use Shredio\DoctrineQueries\Criteria\Criteria;
use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Query\ArrayQueries;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use Shredio\DoctrineQueries\Query\ScalarQueries;
use Stringable;
use Tests\Doctrine\AccountNumber;
use Tests\Doctrine\BinaryId;
use Tests\Doctrine\Symbol;
use Tests\Entity\Article;
use Tests\Entity\Enum\Priority;
use Tests\Entity\InvestmentAccount;
use Tests\Entity\Portfolio;
use Tests\Entity\TypedValues;

class CriteriaValueTypeRuleCases
{

	public ScalarQueries $scalarQueries;
	public ArrayQueries $arrayQueries;
	public ObjectQueries $objectQueries;
	public DoctrineQueries $doctrineQueries;

	/**
	 * @param list<AccountNumber> $numbers
	 * @param iterable<AccountNumber|int> $numbersOrIntegers
	 */
	public function convertedValues(
		AccountNumber $number,
		?AccountNumber $nullableNumber,
		AccountNumber|BinaryId $numberOrId,
		array $numbers,
		iterable $numbersOrIntegers,
		Portfolio $portfolio,
		BinaryId $portfolioId,
		Symbol $symbol,
		Priority $priority,
		mixed $mixed,
		object $object,
	): void
	{
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $number]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $nullableNumber, 'id !=' => null]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => [$number, 5, null]]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $numbers, 'id' => $numbersOrIntegers]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number !=' => $numbersOrIntegers]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => 5, 'id' => '5']); // scalars are bound as they are
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number >' => '5']);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $priority]); // bound by Doctrine
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => [$priority, Priority::High]]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $symbol]); // creates its own parameter
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $mixed, 'id' => $object]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $object]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $numberOrId]); // can be converted
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => [$numberOrId]]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $this->doctrineQueries->subQuery(InvestmentAccount::class, select: ['number'])]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['portfolio' => $portfolio]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['portfolio' => $portfolioId]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['portfolio !=' => [$portfolio, $portfolioId, 'bytes']]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['portfolio.id' => $portfolioId]);
		$this->doctrineQueries->existsBy(Portfolio::class, ['accounts.number' => $number]);
	}

	public function valuesOfBuiltInTypes(
		Stringable $stringable,
		BinaryId $stringableId,
		DateTime $dateTime,
		DateTimeImmutable $dateTimeImmutable,
		Symbol $symbol,
		AccountNumber $number,
	): void
	{
		$this->scalarQueries->findBy(Article::class, ['title' => $stringable, 'content' => [$stringableId]]); // passed through
		$this->scalarQueries->findBy(Article::class, ['id' => $stringableId, 'author' => $stringable]);
		$this->scalarQueries->findBy(Article::class, ['symbol' => $symbol]);
		$this->scalarQueries->findBy(Article::class, ['symbol' => [$symbol]]);
		$this->scalarQueries->findBy(TypedValues::class, ['day' => $dateTime, 'moment >=' => $dateTimeImmutable]);
		$this->scalarQueries->findBy(TypedValues::class, ['day' => [$dateTime, $dateTimeImmutable]]); // a DateTime is converted as DateTimeImmutable
		$this->scalarQueries->findBy(TypedValues::class, ['moment' => [$dateTime], 'time !=' => [$dateTimeImmutable]]);
		$this->scalarQueries->findBy(TypedValues::class, ['payload' => $number, 'flag' => $stringable]); // json converts anything
	}

	/**
	 * @param list<BinaryId> $ids
	 * @param iterable<BinaryId|Symbol> $idsOrSymbols
	 */
	public function refusedValues(
		AccountNumber $number,
		BinaryId $id,
		?BinaryId $nullableId,
		BinaryId|Symbol|null $idOrSymbol,
		array $ids,
		iterable $idsOrSymbols,
		Portfolio $portfolio,
		InvestmentAccount $account,
		Symbol $symbol,
	): void
	{
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $id]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number !=' => $nullableId]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => [$number, $id]]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $ids]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number !=' => $idsOrSymbols]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => [$symbol]]); // converted in a list
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $idOrSymbol === null || $idOrSymbol instanceof BinaryId ? $idOrSymbol : [$idOrSymbol]]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['number' => $portfolio]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['portfolio' => $number]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['portfolio' => [$portfolio, $account]]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, ['portfolio.id' => $portfolio]);
		$this->doctrineQueries->existsBy(Portfolio::class, ['accounts.number' => $id]);
		$this->doctrineQueries->existsBy(TypedValues::class, ['day' => $number]);
		$this->doctrineQueries->existsBy(TypedValues::class, ['moment' => [new DateTimeImmutable(), $number]]);
	}

	public function everyMethod(BinaryId $id): void
	{
		$this->scalarQueries->findBy(InvestmentAccount::class, ['number' => $id]);
		$this->arrayQueries->findOneBy(InvestmentAccount::class, ['number' => $id]);
		$this->objectQueries->findBy(InvestmentAccount::class, ['number' => $id]);
		$this->scalarQueries->findColumnValuesBy(InvestmentAccount::class, 'id', ['number' => $id]);
		$this->arrayQueries->findPairsBy(InvestmentAccount::class, 'id', 'number', ['number' => $id]);
		$this->doctrineQueries->countBy(InvestmentAccount::class, ['number' => $id]);
		$this->doctrineQueries->deleteBy(InvestmentAccount::class, ['number' => $id]);
		$this->doctrineQueries->sumBy(InvestmentAccount::class, 'id', ['number' => $id]);
		$this->doctrineQueries->countGroupedBy(InvestmentAccount::class, 'id', ['number' => $id]);
		$this->doctrineQueries->subQuery(InvestmentAccount::class, ['number' => $id], select: ['id']);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, [Criteria::anyOf(['number' => $id], ['id' => 1])]);
		$this->doctrineQueries->existsBy(InvestmentAccount::class, [Criteria::anyOf(['id' => 1], ['id' => 2, Criteria::anyOf(['id' => 3], ['portfolio' => [$id, 1.5, $this]])])]);
	}

}
