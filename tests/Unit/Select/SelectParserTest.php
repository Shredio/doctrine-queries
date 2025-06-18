<?php declare(strict_types = 1);

namespace Tests\Unit\Select;

use InvalidArgumentException;
use Shredio\DoctrineQueries\Field\EntityMetadata;
use Shredio\DoctrineQueries\Hydration\HydrationType;
use Shredio\DoctrineQueries\Select\SelectParser;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\TestCase;

final class SelectParserTest extends TestCase
{

	use DoctrineContext;

	public function testSimplySelect(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$this->assertSame(['a.title', 'a.content AS text', 'IDENTITY(a.author) AS author'], $parser->getFromSelect($entityMetadata, ['title', 'content' => 'text', 'author'], HydrationType::Array));
	}

	public function testSameColumns(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$this->expectException(InvalidArgumentException::class);
		$parser->getFromSelect($entityMetadata, ['title' => 'text', 'content' => 'text'], HydrationType::Scalar);
	}

	public function testGetFromSelectWithScalarHydrationType(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$result = $parser->getFromSelect($entityMetadata, ['title', 'content'], HydrationType::Scalar);

		$this->assertSame(['a.title AS title', 'a.content AS content'], $result);
	}

	public function testGetFromSelectWithObjectHydrationType(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$result = $parser->getFromSelect($entityMetadata, ['title', 'author'], HydrationType::Object);

		$this->assertSame(['a.title', 'a.author'], $result);
	}

	public function testGetFromSelectWithNestedFields(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$result = $parser->getFromSelect($entityMetadata, ['title', 'author.name' => 'author_name'], HydrationType::Array);

		$this->assertSame(['a.title', 'b.name AS author_name'], $result);
	}

	public function testGetForAllWithObjectHydrationType(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$result = $parser->getForAll($entityMetadata, HydrationType::Object);

		$this->assertSame(['a'], $result);
	}

	public function testGetForAllWithArrayHydrationType(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$result = $parser->getForAll($entityMetadata, HydrationType::Array);

		$this->assertSame(['a'], $result);
	}

	public function testGetForAllWithScalarHydrationType(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$result = $parser->getForAll($entityMetadata, HydrationType::Scalar);

		$this->assertSame(['a.id AS id', 'a.title AS title', 'a.content AS content', 'a.symbol AS symbol', 'a.createdAt AS createdAt'], $result);
	}

	public function testGetForAllWithRelations(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$result = $parser->getForAll($entityMetadata, HydrationType::Scalar, true);

		$expected = [
			'a.id AS id',
			'a.title AS title',
			'a.content AS content',
			'a.symbol AS symbol',
			'a.createdAt AS createdAt',
			'IDENTITY(a.author) AS author'
		];
		$this->assertSame($expected, $result);
	}

	public function testGetForAllWithObjectHydrationAndRelations(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$result = $parser->getForAll($entityMetadata, HydrationType::Object, true);

		$this->assertSame(['a'], $result);
	}

	public function testCreateSelectIdentityForRelation(): void
	{
		$parser = new SelectParser();
		$entityMetadata = new EntityMetadata($this->createManagerRegistry(), Article::class);

		$result = $parser->getFromSelect($entityMetadata, ['author' => 'author_id'], HydrationType::Scalar);

		$this->assertSame(['IDENTITY(a.author) AS author_id'], $result);
	}

	protected function setUpDatabase(): bool
	{
		return false;
	}

}
