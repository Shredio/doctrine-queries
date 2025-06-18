<?php declare(strict_types = 1);

namespace Tests\Unit\Field;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Shredio\DoctrineQueries\Field\FieldPath;
use Shredio\DoctrineQueries\Field\EntityMetadata;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\Author;

final class MappedFieldPathFactoryTest extends TestCase
{
	use DoctrineContext;

	private EntityMetadata $factory;

	protected function setUp(): void
	{
		$this->factory = new EntityMetadata($this->createManagerRegistry(), Article::class);
	}

	public function testCreateSimpleField(): void
	{
		$fieldPath = FieldPath::createFromString('title');
		$result = $this->factory->createField($fieldPath);

		$this->assertSame('a', $result->entityAlias);
		$this->assertSame(Article::class, $result->entity);
		$this->assertSame('title', $result->name);
		$this->assertSame('a.title', $result->path);
		$this->assertFalse($result->isRelation);
	}

	public function testCreateAssociationField(): void
	{
		$fieldPath = FieldPath::createFromString('author');
		$result = $this->factory->createField($fieldPath);

		$this->assertSame('a', $result->entityAlias);
		$this->assertSame(Article::class, $result->entity);
		$this->assertSame('author', $result->name);
		$this->assertSame('a.author', $result->path);
		$this->assertTrue($result->isRelation);
	}

	public function testCreateNestedField(): void
	{
		$fieldPath = FieldPath::createFromString('author.name');
		$result = $this->factory->createField($fieldPath);

		$this->assertSame('b', $result->entityAlias);
		$this->assertSame(Author::class, $result->entity);
		$this->assertSame('name', $result->name);
		$this->assertSame('b.name', $result->path);
		$this->assertFalse($result->isRelation);
	}

	public function testCreateNestedFieldWithId(): void
	{
		$fieldPath = FieldPath::createFromString('author.id');
		$result = $this->factory->createField($fieldPath);

		$this->assertSame('b', $result->entityAlias);
		$this->assertSame(Author::class, $result->entity);
		$this->assertSame('id', $result->name);
		$this->assertSame('b.id', $result->path);
		$this->assertFalse($result->isRelation);
	}

	public function testCachingWithParentPath(): void
	{
		// First call to establish cache
		$fieldPath1 = FieldPath::createFromString('author.name');
		$result1 = $this->factory->createField($fieldPath1);

		// Second call should use cache for parent path
		$fieldPath2 = FieldPath::createFromString('author.id');
		$result2 = $this->factory->createField($fieldPath2);

		// Both should use the same alias for Author entity
		$this->assertSame('b', $result1->entityAlias);
		$this->assertSame('b', $result2->entityAlias);
		$this->assertSame(Author::class, $result1->entity);
		$this->assertSame(Author::class, $result2->entity);
		$this->assertSame('name', $result1->name);
		$this->assertSame('id', $result2->name);
	}

	public function testEntityAliasIncrement(): void
	{
		$fieldPath1 = FieldPath::createFromString('title');
		$result1 = $this->factory->createField($fieldPath1);

		$fieldPath2 = FieldPath::createFromString('author.name');
		$result2 = $this->factory->createField($fieldPath2);

		$this->assertSame('a', $result1->entityAlias);
		$this->assertSame('b', $result2->entityAlias);
		$this->assertSame(Article::class, $result1->entity);
		$this->assertSame(Author::class, $result2->entity);
	}

	public function testThrowsExceptionForUnknownField(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Field "unknown" not found in entity');

		$fieldPath = FieldPath::createFromString('unknown');
		$this->factory->createField($fieldPath);
	}

	protected function setUpDatabase(): bool
	{
		return false; // We don't need actual database for these tests
	}

}
