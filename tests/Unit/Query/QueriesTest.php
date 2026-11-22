<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use LogicException;
use Shredio\DoctrineQueries\Exception\FieldNotExistsException;
use Shredio\DoctrineQueries\Exception\MultipleFieldSelectionException;
use Shredio\DoctrineQueries\Pagination\Pagination;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Doctrine\Symbol;
use Tests\Entity\Article;
use Tests\Entity\ArticleHashtag;
use Tests\Entity\Author;
use Tests\Entity\FavouritePrompt;
use Tests\Entity\Prompt;
use Tests\TestCase;
use Tests\Unit\Helpers;

/**
 * countBy(), existsBy() and deleteBy() of DoctrineQueries.
 */
final class QueriesTest extends TestCase
{

	use DoctrineContext;
	use Helpers;

	public function testCountBy(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(3, $this->assertSingleQuery(fn (): int => $queries->countBy(Article::class)));
		$this->assertSame(1, $queries->countBy(Article::class, ['id' => 1]));
		$this->assertSame(0, $queries->countBy(Article::class, ['id' => 0]));
		$this->assertSame(2, $queries->countBy(Article::class, ['author.name' => 'John Doe']));
	}

	public function testCountByField(): void
	{
		$this->persistFixtures(static fn (Author $johnDoe): Article => new Article(4, 'Fourth Article', 'This is the fourth article.', $johnDoe, new Symbol('sym')));
		$queries = $this->createDoctrineQueries();

		$this->assertSame(2, $queries->countBy(Article::class, ['symbol' => 'sym'], 'author.id'));
		$this->assertSame(1, $queries->countBy(Article::class, ['symbol' => 'sym'], 'author.id', distinct: true));
		$this->assertSame(2, $queries->countBy(Article::class, field: 'symbol'), 'COUNT() of a field skips NULL values.');
		$this->assertSame(1, $queries->countBy(Article::class, field: 'author.role.id'));
		$this->assertSame(4, $queries->countBy(Article::class, field: 'author'));
		$this->assertSame(2, $queries->countBy(Article::class, field: 'author', distinct: true));
		$this->assertSame(1, $queries->countBy(Article::class, field: 'author.role'), 'COUNT() of an association skips rows without it.');
	}

	public function testCountByJoinConfig(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(3, $queries->countBy(Article::class, field: 'id', joinConfig: 'left'));
		$this->assertSame(2, $queries->countBy(Article::class, ['author.role.id' => null]), 'The left join keeps authors without a role.');
		$this->assertSame(0, $queries->countBy(Article::class, ['author.role.id' => null], joinConfig: ['author.role' => 'inner']));
		$this->assertSame(1, $queries->countBy(Article::class, ['author.role.name' => 'Administrator'], joinConfig: 'inner'));
	}

	public function testCountByCompositeIdentifier(): void
	{
		$this->persistFavouritePrompts();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(3, $queries->countBy(FavouritePrompt::class));
		$this->assertSame(2, $queries->countBy(FavouritePrompt::class, ['prompt' => 1]));
		$this->assertSame(2, $queries->countBy(FavouritePrompt::class, field: 'account', distinct: true));
	}

	public function testCountByRejectsWildcardField(): void
	{
		$this->persistFixtures();

		$this->expectException(MultipleFieldSelectionException::class);

		$this->createDoctrineQueries()->countBy(Article::class, field: '*');
	}

	public function testCountByRejectsUnknownField(): void
	{
		$this->persistFixtures();

		$this->expectException(FieldNotExistsException::class);

		$this->createDoctrineQueries()->countBy(Article::class, field: 'unknown');
	}

	public function testExistsBy(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$getQueries = $this->captureQueries();
		$this->assertTrue($queries->existsBy(Article::class, ['id' => 1]));
		$executedQueries = $getQueries();

		$this->assertCount(1, $executedQueries);
		$this->assertStringStartsWith('SELECT 1 AS ', $executedQueries[0], 'No column of the entity is fetched.');
		$this->assertStringContainsString(' LIMIT 1', $executedQueries[0]);
		$this->assertFalse($queries->existsBy(Article::class, ['id' => 0]));
		$this->assertTrue($queries->existsBy(Article::class, ['author.role.name' => 'Administrator']));
		$this->assertFalse($queries->existsBy(Article::class, ['author.role.name' => 'Editor']));
		$this->assertTrue($queries->existsBy(Article::class, []));
	}

	public function testExistsByOnEmptyTable(): void
	{
		$this->assertFalse($this->createDoctrineQueries()->existsBy(Article::class, []));
	}

	public function testCriteriaOnToManyAssociation(): void
	{
		$this->persistFixtures();
		$this->persistHashtags();
		$queries = $this->createDoctrineQueries();
		$criteria = ['hashTags.hashtag' => ['php', 'doctrine']];
		$orderBy = ['id' => SortDirection::Ascending];

		$this->assertSame(2, $queries->countBy(Article::class, $criteria), 'Each article is counted once although it matches more hashtags.');
		$this->assertSame(2, $queries->countBy(Article::class, $criteria, 'id'), 'The criteria do not join the hashtags, so the rows are not multiplied.');
		$this->assertSame(3, $queries->countBy(Article::class, $criteria, 'hashTags.hashtag'), 'Counting a field of the hashtags counts the matching hashtags.');
		$this->assertTrue($queries->existsBy(Article::class, $criteria));

		$this->assertSame(
			[1, 2],
			array_map(static fn (Article $article): int => $article->getId(), $queries->objects->findBy(Article::class, $criteria, $orderBy)->asArray()),
			'Object hydration returns each root entity once.',
		);
		$this->assertSame(
			[1, 2],
			self::column($queries->arrays->findBy(Article::class, $criteria, $orderBy)->asArray(), 'id'),
			'Array hydration returns each root entity once too.',
		);
		$this->assertSame(
			[1, 2],
			self::column($queries->scalars->findBy(Article::class, $criteria, $orderBy, select: ['id'])->asArray(), 'id'),
		);
		$this->assertSame([1, 2], $queries->scalars->findColumnValuesBy(Article::class, 'id', $criteria, $orderBy, distinct: true)->asArray());
		$this->assertSame(
			[1, 2],
			array_map(
				static fn (Article $article): int => $article->getId(),
				$queries->objects->findBy(Article::class, $criteria, $orderBy, pagination: new Pagination(2))->asArray(),
			),
			'The limit counts the articles, the first one matching two hashtags takes a single row.',
		);
	}

	public function testSelectOfToManyRelatedField(): void
	{
		$this->persistFixtures();
		$this->persistHashtags();

		$rows = $this->createDoctrineQueries()->scalars->findBy(
			Article::class,
			orderBy: ['id' => SortDirection::Ascending, 'hashTags.hashtag' => SortDirection::Ascending],
			select: ['id', 'hashTags.hashtag'],
		)->asArray();

		$this->assertSame([
			['id' => 1, 'hashtag' => 'doctrine'],
			['id' => 1, 'hashtag' => 'php'],
			['id' => 2, 'hashtag' => 'php'],
			['id' => 3, 'hashtag' => 'symfony'],
		], $rows);
	}

	public function testDeleteBy(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(1, $this->assertSingleQuery(fn (): int => $queries->deleteBy(Article::class, ['id' => 1])));
		$this->assertSame([2, 3], $queries->scalars->findColumnValuesBy(Article::class, 'id', orderBy: ['id' => SortDirection::Ascending])->asArray());

		$this->assertSame(0, $queries->deleteBy(Article::class, ['id' => 999]));
		$this->assertSame(2, $queries->countBy(Article::class));
	}

	public function testDeleteByCriteria(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(1, $queries->deleteBy(Article::class, ['title LIKE' => '%r Article', 'symbol' => null]), 'Only "Another Article" ends with "r Article".');
		$this->assertSame([1, 3], $queries->scalars->findColumnValuesBy(Article::class, 'id', orderBy: ['id' => SortDirection::Ascending])->asArray());
	}

	public function testDeleteByWithoutCriteriaDeletesEverything(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(3, $queries->deleteBy(Article::class));
		$this->assertSame(0, $queries->countBy(Article::class));
	}

	public function testDeleteByEmptyInDeletesNothing(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(0, $queries->deleteBy(Article::class, ['id' => []]));
		$this->assertSame(3, $queries->countBy(Article::class));
	}

	/**
	 * An empty NOT IN matches every row, so it restricts a delete as little as a finder.
	 */
	public function testDeleteByEmptyNotInDeletesEverything(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(1, $queries->deleteBy(Article::class, ['id !=' => [], 'title' => 'Sample Article']), 'The other criteria still apply.');
		$this->assertSame(2, $queries->deleteBy(Article::class, ['id !=' => []]));
		$this->assertSame(0, $queries->countBy(Article::class));
	}

	public function testDeleteByAssociationIdentifier(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$this->assertSame(2, $queries->deleteBy(Article::class, ['author' => 1]));
		$this->assertSame([2], $queries->scalars->findColumnValuesBy(Article::class, 'id', orderBy: ['id' => SortDirection::Ascending])->asArray());
	}

	public function testDeleteByRelatedFieldIsRejected(): void
	{
		$this->persistFixtures();

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('Cannot delete Tests\Entity\Article by criteria "author.name LIKE", DQL DELETE does not support joins.');

		$this->createDoctrineQueries()->deleteBy(Article::class, ['author.name LIKE' => 'John%']);
	}

	public function testDeleteByRelatedFieldThroughSubQuery(): void
	{
		$this->persistFixtures();
		$queries = $this->createDoctrineQueries();

		$deletedCount = $this->assertSingleQuery(fn (): int => $queries->deleteBy(Article::class, [
			'author' => $queries->subQuery(Author::class, ['role.name' => 'Administrator']),
		]));

		$this->assertSame(1, $deletedCount);
		$this->assertSame([1, 3], $queries->scalars->findColumnValuesBy(Article::class, 'id', orderBy: ['id' => SortDirection::Ascending])->asArray());
	}

	private function persistHashtags(): void
	{
		$em = $this->getEntityManager();
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'php'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'doctrine'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 2), 'php'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 3), 'symfony'));
		$em->flush();
		$em->clear();
	}

	private function persistFavouritePrompts(): void
	{
		$em = $this->getEntityManager();
		$em->persist($first = new Prompt(1, 'First', 'First prompt'));
		$em->persist($second = new Prompt(2, 'Second', 'Second prompt'));
		$em->persist(new FavouritePrompt(10, $first));
		$em->persist(new FavouritePrompt(10, $second));
		$em->persist(new FavouritePrompt(20, $first));
		$em->flush();
		$em->clear();
	}

}
