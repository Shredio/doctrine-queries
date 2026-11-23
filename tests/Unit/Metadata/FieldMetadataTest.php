<?php declare(strict_types = 1);

namespace Tests\Unit\Metadata;

use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Exception\UnsupportedFieldTypeException;
use Shredio\DoctrineQueries\Metadata\FieldType;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\FavouritePromptReference;
use Tests\Entity\Note;
use Tests\Entity\Enum\Priority;
use Tests\Entity\OneToOnePrimary;
use Tests\Entity\UsageRecord;
use Tests\TestCase;

final class FieldMetadataTest extends TestCase
{

	use DoctrineContext;

	/**
	 * @return iterable<string, array{class-string, non-empty-string, FieldType}>
	 */
	public static function provideFieldTypeOfAssociation(): iterable
	{
		yield 'many-to-one' => [Article::class, 'author', new FieldType('integer', false, null, true, true)];
		yield 'owning side of one-to-one' => [OneToOnePrimary::class, 'assoc', new FieldType('integer', false, null, true, true)];
		yield 'referencing an unsigned identifier' => [UsageRecord::class, 'previous', new FieldType('integer', true, null, true, true, true)];
	}

	/**
	 * @return iterable<string, array{non-empty-string, FieldType}>
	 */
	public static function provideFieldTypeOfUsageRecord(): iterable
	{
		yield 'unsigned integer' => ['tokens', new FieldType('integer', false, null, false, unsigned: true)];
		yield 'nullable unsigned smallint' => ['retries', new FieldType('smallint', true, null, false, unsigned: true)];
		yield 'unsigned enum' => ['priority', new FieldType('smallint', false, Priority::class, false, unsigned: true)];
		yield 'signed integer' => ['balance', new FieldType('integer', false, null, false)];
		yield 'unsigned field of a related entity' => ['previous.tokens', new FieldType('integer', true, null, true, unsigned: true)];
	}

	/**
	 * @param non-empty-string $field
	 */
	#[DataProvider('provideFieldTypeOfUsageRecord')]
	public function testUnsignedOptionOfField(string $field, FieldType $expectedType): void
	{
		$this->assertEquals($expectedType, $this->getMetadata(UsageRecord::class, QueryType::Scalar)->getFieldMetadata(new Field($field))->getFieldType(false));
	}

	/**
	 * @param class-string $entity
	 * @param non-empty-string $field
	 */
	#[DataProvider('provideFieldTypeOfAssociation')]
	public function testFieldTypeOfAssociation(string $entity, string $field, FieldType $expectedType): void
	{
		$this->assertEquals($expectedType, $this->getMetadata($entity, QueryType::Scalar)->getFieldMetadata(new Field($field))->getFieldType());
	}

	/**
	 * @return iterable<string, array{class-string, non-empty-string, non-empty-string}>
	 */
	public static function provideUnsupportedAssociation(): iterable
	{
		yield 'composite foreign key of many-to-one' => [
			FavouritePromptReference::class,
			'favourite',
			'Composite foreign key of Tests\Entity\FavouritePromptReference::$favourite is not supported.',
		];
		yield 'composite foreign key of one-to-one' => [
			FavouritePromptReference::class,
			'pinnedFavourite',
			'Composite foreign key of Tests\Entity\FavouritePromptReference::$pinnedFavourite is not supported.',
		];
		yield 'one-to-many' => [
			Article::class,
			'hashTags',
			'Association Tests\Entity\Article::$hashTags is not supported, only ManyToOne and owning side of OneToOne associations are.',
		];
		yield 'many-to-many' => [
			Note::class,
			'tags',
			'Association Tests\Entity\Note::$tags is not supported, only ManyToOne and owning side of OneToOne associations are.',
		];
	}

	/**
	 * @param class-string $entity
	 * @param non-empty-string $field
	 */
	#[DataProvider('provideUnsupportedAssociation')]
	public function testUnsupportedAssociation(string $entity, string $field, string $expectedMessage): void
	{
		$fieldMetadata = $this->getMetadata($entity, QueryType::Scalar)->getFieldMetadata(new Field($field));

		$this->expectException(UnsupportedFieldTypeException::class);
		$this->expectExceptionMessage($expectedMessage);

		$fieldMetadata->getFieldType();
	}

	protected function setUpDatabase(): bool
	{
		return false;
	}

}
