<?php declare(strict_types = 1);

namespace Tests\Unit\Metadata;

use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Select\Field;
use Shredio\DoctrineQueries\Select\QueryType;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\Entity\ProjectMember;
use Tests\Entity\ProjectRole;
use Tests\Entity\Role;
use Tests\TestCase;

final class FieldMetadataFactoryTest extends TestCase
{

	use DoctrineContext;

	/**
	 * ProjectMember::$role points to ProjectRole while ProjectMember::$author::$role points to Role. A nested path
	 * must never be cached under its last segment, otherwise "role" would resolve to the Role of "author.role".
	 */
	public function testNestedPathsAreCachedUnderTheirFullPath(): void
	{
		$metadata = $this->getMetadata(ProjectMember::class, QueryType::Array);

		$metadata->getFieldMetadata(new Field('author.name')); // caches "author"
		$metadata->getFieldMetadata(new Field('author.role.name')); // resolved from the cached "author"

		$this->assertSame('label', $metadata->getFieldMetadata(new Field('role.label'))->field->name);

		$this->expectException(FieldNotExistsException::class);
		$this->expectExceptionMessage('Field "name" does not exist in entity "Tests\Entity\ProjectRole".');

		$metadata->getFieldMetadata(new Field('role.name'));
	}

	public function testSameAssociationNameInDifferentEntitiesInOneQuery(): void
	{
		$em = $this->getEntityManager();
		$em->persist($administrator = new Role(1, 'Administrator'));
		$em->persist($author = new Author(1, 'Jane Smith', $administrator));
		$em->persist($maintainer = new ProjectRole(1, 'Maintainer'));
		$em->persist(new ProjectMember(1, $author, $maintainer));
		$em->flush();
		$em->clear();

		$rows = $this->createDoctrineQueries()->scalars->findBy(
			ProjectMember::class,
			['role.label' => 'Maintainer'],
			orderBy: ['author.role.name' => SortDirection::Ascending],
			select: ['author.name', 'author.role.name' => 'authorRole', 'role.label' => 'projectRole'],
		)->asArray();

		$this->assertSame([['name' => 'Jane Smith', 'authorRole' => 'Administrator', 'projectRole' => 'Maintainer']], $rows);
	}

	public function testInvalidAssociationPath(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);
		$metadata->getFieldMetadata(new Field('author.name'));

		try {
			$metadata->getFieldMetadata(new Field('author.rol.name'));
			$this->fail('InvalidAssociationPathException was expected.');
		} catch (InvalidAssociationPathException $exception) {
			$this->assertSame(Author::class, $exception->entity);
			$this->assertSame('author.rol', $exception->path);
			$this->assertSame('rol', $exception->fieldName);
		}
	}

	public function testFieldIsNotAnAssociation(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		try {
			$metadata->getFieldMetadata(new Field('title.length'));
			$this->fail('InvalidAssociationPathException was expected.');
		} catch (InvalidAssociationPathException $exception) {
			$this->assertSame(Article::class, $exception->entity);
			$this->assertSame('title', $exception->path);
			$this->assertSame('title', $exception->fieldName);
		}
	}

	public function testToManyAssociation(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		$this->assertTrue($metadata->getFieldMetadata(new Field('hashTags'))->isToManyAssociation());
		$this->assertFalse($metadata->getFieldMetadata(new Field('author'))->isToManyAssociation());
		$this->assertFalse($metadata->getFieldMetadata(new Field('title'))->isToManyAssociation());

		$this->assertFalse($metadata->hasToManyJoin());
		$metadata->getPathForField(new Field('author.name'));
		$this->assertFalse($metadata->hasToManyJoin());
		$metadata->getPathForField(new Field('hashTags.hashtag'));
		$this->assertTrue($metadata->hasToManyJoin());
	}

}
