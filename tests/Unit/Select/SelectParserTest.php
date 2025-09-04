<?php declare(strict_types = 1);

namespace Tests\Unit\Select;

use InvalidArgumentException;
use Shredio\DoctrineQueries\Exception\NonUniqueSelectAliasException;
use Shredio\DoctrineQueries\Select\QueryType;
use Shredio\DoctrineQueries\Select\SelectParser;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\TestCase;

final class SelectParserTest extends TestCase
{

	use DoctrineContext;

	public function testSimplySelect(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		$this->assertSame(['e0.title', 'e0.content AS text', 'IDENTITY(e0.author) AS author'], SelectParser::getForSelection($metadata, ['title', 'content' => 'text', 'author'], QueryType::Object));
	}

	public function testSameColumns(): void
	{
		$metadata = $this->getMetadata(Article::class, QueryType::Array);

		$this->expectException(NonUniqueSelectAliasException::class);
		SelectParser::getForSelection($metadata, ['title' => 'text', 'content' => 'text'], QueryType::Object);
	}

	protected function setUpDatabase(): bool
	{
		return false;
	}

}
