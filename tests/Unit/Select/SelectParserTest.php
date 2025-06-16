<?php declare(strict_types = 1);

namespace Tests\Unit\Select;

use InvalidArgumentException;
use Shredio\DoctrineQueries\Select\SelectParser;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\TestCase;

final class SelectParserTest extends TestCase
{

	use DoctrineContext;

	public function testSimplySelect(): void
	{
		$parser = new SelectParser();
		$metadata = $this->getMetadata(Article::class);

		$this->assertSame(['a.title', 'a.content AS text', 'IDENTITY(a.author) AS author'], $parser->getFromSelect($metadata, ['title', 'content' => 'text', 'author'], 'a'));
	}

	public function testSameColumns(): void
	{
		$parser = new SelectParser();
		$metadata = $this->getMetadata(Article::class);

		$this->expectException(InvalidArgumentException::class);
		$parser->getFromSelect($metadata, ['title' => 'text', 'content' => 'text'], 'a');
	}

	protected function setUpDatabase(): bool
	{
		return false;
	}

}
