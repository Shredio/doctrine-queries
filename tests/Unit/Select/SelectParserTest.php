<?php declare(strict_types = 1);

namespace Tests\Unit\Select;

use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\InvalidAssociationPathException;
use Shredio\DoctrineQueries\Exception\InvalidSelectAliasException;
use Shredio\DoctrineQueries\Exception\NonUniqueSelectAliasException;
use Shredio\DoctrineQueries\Select\FieldToSelect;
use Shredio\DoctrineQueries\Select\QueryType;
use Shredio\DoctrineQueries\Select\SelectParser;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\TestCase;
use Throwable;

final class SelectParserTest extends TestCase
{

	use DoctrineContext;

	private const array ScalarRootFields = [
		'e0.id AS id',
		'e0.title AS title',
		'e0.content AS content',
		'e0.symbol AS symbol',
		'e0.createdAt AS createdAt',
		'e0.type AS type',
	];

	/**
	 * @return iterable<string, array{QueryType, list<string>|array<string, string>, list<string>}>
	 */
	public static function provideSelections(): iterable
	{
		yield 'default selection (array)' => [QueryType::Array, [], ['e0.id', 'e0.title', 'e0.content', 'e0.symbol', 'e0.createdAt', 'e0.type']];
		yield 'default selection (object)' => [QueryType::Object, [], ['e0']];
		yield 'default selection (sub-query)' => [QueryType::SubQuery, [], ['e0']];
		yield 'default selection (scalar)' => [QueryType::Scalar, [], self::ScalarRootFields];

		$fieldsAliasAndAssociation = ['title', 'content' => 'text', 'author'];
		yield 'fields, alias and association (array)' => [QueryType::Array, $fieldsAliasAndAssociation, ['e0.title', 'e0.content AS text', 'IDENTITY(e0.author) AS author']];
		yield 'fields, alias and association (object)' => [QueryType::Object, $fieldsAliasAndAssociation, ['e0.title', 'e0.content AS text', 'IDENTITY(e0.author) AS author']];
		yield 'fields, alias and association (scalar)' => [QueryType::Scalar, $fieldsAliasAndAssociation, ['e0.title AS title', 'e0.content AS text', 'IDENTITY(e0.author) AS author']];
		yield 'fields, alias and association (sub-query)' => [QueryType::SubQuery, $fieldsAliasAndAssociation, ['e0.title', 'e0.content', 'IDENTITY(e0.author)']];

		yield 'related fields (array)' => [QueryType::Array, ['author.name', 'author.role.name' => 'role'], ['e1.name', 'e2.name AS role']];
		yield 'related fields (scalar)' => [QueryType::Scalar, ['author.name', 'author.role.name' => 'role'], ['e1.name AS name', 'e2.name AS role']];
		yield 'related fields (sub-query)' => [QueryType::SubQuery, ['author.id'], ['e1.id']];

		yield 'root wildcard (array)' => [QueryType::Array, ['*'], ['e0.id', 'e0.title', 'e0.content', 'e0.symbol', 'e0.createdAt', 'e0.type']];
		yield 'root wildcard (scalar)' => [QueryType::Scalar, ['*'], self::ScalarRootFields];
		yield 'root wildcard with relations (scalar)' => [QueryType::Scalar, ['**'], [...self::ScalarRootFields, 'IDENTITY(e0.author) AS author']];
		yield 'relation wildcard (array)' => [QueryType::Array, ['author.*'], ['e1.id', 'e1.name']];
		yield 'relation wildcard with relations (array)' => [QueryType::Array, ['author.**'], ['e1.id', 'e1.name', 'IDENTITY(e1.role) AS role']];
		yield 'prefixed relation wildcard (array)' => [QueryType::Array, ['author.**' => 'author_'], ['e1.id AS author_id', 'e1.name AS author_name', 'IDENTITY(e1.role) AS author_role']];
		yield 'prefixed relation wildcard (scalar)' => [QueryType::Scalar, ['id', 'author.*' => 'author_'], ['e0.id AS id', 'e1.id AS author_id', 'e1.name AS author_name']];
	}

	/**
	 * @param list<string>|array<string, string> $select
	 * @param list<string> $expected
	 */
	#[DataProvider('provideSelections')]
	public function testGetForSelection(QueryType $queryType, array $select, array $expected): void
	{
		$metadata = $this->getMetadata(Article::class, $queryType);

		$this->assertSame($expected, SelectParser::getForSelection($metadata, $select, $queryType));
	}

	/**
	 * @return iterable<string, array{list<string>|array<string, string>, class-string<\Throwable>}>
	 */
	public static function provideInvalidSelections(): iterable
	{
		yield 'same alias' => [['title' => 'text', 'content' => 'text'], NonUniqueSelectAliasException::class];
		yield 'same field name in different entities' => [['id', 'author.id'], NonUniqueSelectAliasException::class];
		yield 'wildcard clashing with a field' => [['*', 'author.name' => 'title'], NonUniqueSelectAliasException::class];
		yield 'unknown field' => [['unknown'], FieldNotExistsException::class];
		yield 'unknown related field' => [['author.unknown'], FieldNotExistsException::class];
		yield 'field used as an association' => [['title.length'], InvalidAssociationPathException::class];
		yield 'unknown association wildcard' => [['unknown.*'], InvalidAssociationPathException::class];
		yield 'alias with an expression' => [['title' => 't, e0.content AS c'], InvalidSelectAliasException::class];
		yield 'alias with a space' => [['title' => 'my title'], InvalidSelectAliasException::class];
		yield 'alias starting with a digit' => [['title' => '1title'], InvalidSelectAliasException::class];
		yield 'alias with a trailing newline' => [['title' => "title\n"], InvalidSelectAliasException::class];
		yield 'empty alias' => [['title' => ''], InvalidSelectAliasException::class];
		yield 'wildcard prefix with an expression' => [['author.*' => 'a, e0.content AS '], InvalidSelectAliasException::class];
	}

	public function testValidAliases(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		$this->assertSame(
			['e0.title AS _title', 'e0.content AS Content2', 'e1.id AS author_id', 'e1.name AS author_name'],
			SelectParser::getForSelection($metadata, ['title' => '_title', 'content' => 'Content2', 'author.*' => 'author_'], QueryType::Array),
		);
	}

	/**
	 * @param list<string>|array<string, string> $select
	 * @param class-string<\Throwable> $expectedException
	 */
	#[DataProvider('provideInvalidSelections')]
	public function testInvalidSelection(array $select, string $expectedException): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		$this->expectException($expectedException);

		SelectParser::getForSelection($metadata, $select, QueryType::Array);
	}

	public function testCollectedDuplicateDoesNotStopTheWildcard(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		$errors = [];
		$selection = iterator_to_array(SelectParser::getMetadataForSelection($metadata, ['id', '*'], $errors), false);

		$this->assertSame(['id', 'title', 'content', 'symbol', 'createdAt', 'type'], array_map(static fn (FieldToSelect $field): string => $field->alias, $selection));
		$this->assertCount(1, $errors);
	}

	public function testEachInvalidAliasOfAWildcardIsCollected(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		$errors = [];
		$selection = iterator_to_array(SelectParser::getMetadataForSelection($metadata, ['author.*' => 'author ', 'title'], $errors), false);

		$this->assertSame(['title'], array_map(static fn (FieldToSelect $field): string => $field->alias, $selection));
		$this->assertSame(
			['author id', 'author name'],
			array_map(static fn (Throwable $error): string => $error instanceof InvalidSelectAliasException ? $error->alias : $error::class, $errors),
		);
	}

	public function testErrorsAreThrownWithoutCollector(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		$this->expectException(FieldNotExistsException::class);

		iterator_to_array(SelectParser::getMetadataForSelection($metadata, ['unknown']), false);
	}

	public function testErrorsCanBeCollected(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		$errors = [];
		$selection = iterator_to_array(SelectParser::getMetadataForSelection($metadata, ['id', 'unknown', 'title' => 'id', 'author.rol.name', 'title'], $errors), false);

		$this->assertSame(['id', 'title'], array_map(static fn (FieldToSelect $field): string => $field->alias, $selection));
		$this->assertCount(3, $errors);
		$this->assertInstanceOf(FieldNotExistsException::class, $errors[0]);
		$this->assertInstanceOf(NonUniqueSelectAliasException::class, $errors[1]);
		$this->assertSame('id', $errors[1]->alias);
		$this->assertInstanceOf(InvalidAssociationPathException::class, $errors[2]);
	}

	protected function setUpDatabase(): bool
	{
		return false;
	}

}
