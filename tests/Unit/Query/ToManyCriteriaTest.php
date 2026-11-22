<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use Shredio\DoctrineQueries\DoctrineQueries;
use Shredio\DoctrineQueries\Pagination\Pagination;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\ArticleHashtag;
use Tests\Entity\Author;
use Tests\Entity\Note;
use Tests\Entity\NoteTag;
use Tests\Entity\ProjectMember;
use Tests\Entity\ProjectRole;
use Tests\Entity\Role;
use Tests\TestCase;
use Tests\Unit\Helpers;

/**
 * Criteria reached through a to-many association filter the root entities by an EXISTS sub-query,
 * unless the selection or the ordering joins the association.
 *
 * Hashtags created by createQueriesWithHashtags():
 *
 * | Article | hashtags      |
 * |---------|---------------|
 * | 1       | php, doctrine |
 * | 2       | php           |
 * | 3       | -             |
 */
final class ToManyCriteriaTest extends TestCase
{

	use DoctrineContext;
	use Helpers;

	private const array PhpOrDoctrine = ['hashTags.hashtag' => ['php', 'doctrine']];

	private const array OrderById = ['id' => SortDirection::Ascending];

	public function testResultsContainEachRootEntityOnce(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1, 2],
			array_map(
				static fn (Article $article): int => $article->getId(),
				iterator_to_array($queries->objects->findBy(Article::class, self::PhpOrDoctrine, self::OrderById)->yield(), false),
			),
		);
		$this->assertSame([1, 2], self::column($queries->arrays->findBy(Article::class, self::PhpOrDoctrine, self::OrderById)->yield(), 'id'));
		$this->assertSame([1, 2], $queries->scalars->findColumnValuesBy(Article::class, 'id', self::PhpOrDoctrine, self::OrderById)->asArray());
		$this->assertSame(
			[1 => 'Sample Article', 2 => 'Another Article'],
			$queries->scalars->findPairsBy(Article::class, 'id', 'title', self::PhpOrDoctrine, self::OrderById)->asArray(),
		);
		$this->assertSame(
			[1, 2],
			array_keys($queries->arrays->findIndexedBy(Article::class, 'id', self::PhpOrDoctrine, self::OrderById, select: ['title'])->asArray()),
		);
		$this->assertSame(2, $queries->countBy(Article::class, self::PhpOrDoctrine));
		$this->assertTrue($queries->existsBy(Article::class, self::PhpOrDoctrine));
	}

	public function testPaginationCountsRootEntities(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1, 2],
			self::column($queries->arrays->findBy(Article::class, self::PhpOrDoctrine, self::OrderById, pagination: new Pagination(2))->asArray(), 'id'),
		);
		$this->assertSame(
			[2],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', self::PhpOrDoctrine, self::OrderById, pagination: new Pagination(1, 1))->asArray(),
		);
	}

	public function testCriteriaOfOneAssociationMatchTheSameRelatedEntity(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', ['hashTags.hashtag' => 'php', 'hashTags.hashtag LIKE' => 'd%'])->asArray(),
			'The first article has "php" and "doctrine", but no hashtag is both.',
		);
		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', ['hashTags.hashtag LIKE' => '%o%', 'hashTags.hashtag !=' => 'php'])->asArray(),
		);
	}

	public function testCriteriaFilterTheRowsOfASelectedAssociation(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			['php', 'php'],
			$queries->scalars->findColumnValuesBy(Article::class, 'hashTags.hashtag', ['hashTags.hashtag LIKE' => 'p%'], self::OrderById)->asArray(),
			'Only the matching hashtags are selected, not all hashtags of the matching articles.',
		);
		$this->assertSame(
			[['id' => 1, 'hashtag' => 'php'], ['id' => 2, 'hashtag' => 'php']],
			iterator_to_array($queries->scalars->findBy(Article::class, ['hashTags.hashtag' => 'php'], self::OrderById, select: ['id', 'hashTags.hashtag'])->yield(), false),
			'A query joining a to-many association can be iterated too.',
		);
		$this->assertSame(1, $queries->countBy(Article::class, ['hashTags.hashtag' => 'doctrine'], 'hashTags.hashtag'));
	}

	public function testCriteriaFilterTheRowsOfAnOrderedAssociation(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1, 1, 2],
			$queries->scalars->findColumnValuesBy(
				Article::class,
				'id',
				self::PhpOrDoctrine,
				['id' => SortDirection::Ascending, 'hashTags.hashtag' => SortDirection::Ascending],
			)->asArray(),
			'Ordering by a field of the hashtags joins them, a row per matching hashtag.',
		);
	}

	public function testNullCriteriaMatchRootEntitiesWithoutRelatedEntity(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame([3], $queries->scalars->findColumnValuesBy(Article::class, 'id', ['hashTags.hashtag' => null])->asArray());
		$this->assertSame([1, 3], $queries->scalars->findColumnValuesBy(Article::class, 'id', ['hashTags.hashtag' => ['doctrine', null]], self::OrderById)->asArray());
		$this->assertSame([1, 2], $queries->scalars->findColumnValuesBy(Article::class, 'id', ['hashTags.hashtag !=' => null], self::OrderById)->asArray());
		$this->assertSame(
			[],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', ['hashTags.hashtag' => null], joinConfig: ['hashTags' => 'inner'])->asArray(),
			'An inner join of the hashtags requires one.',
		);
		$this->assertSame(1, $queries->countBy(Article::class, ['hashTags.hashtag' => null]));
	}

	public function testNestedToManyAssociations(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(Article::class, 'id', ['hashTags.article.hashTags.hashtag' => 'doctrine'])->asArray(),
			'The hashtags of the article of a hashtag are checked by a nested sub-query.',
		);
	}

	public function testToManyAssociationOfRelatedEntity(): void
	{
		$this->persistFixtures(static function (Author $johnDoe, Role $administrator, Author $janeSmith): array {
			$maintainer = new ProjectRole(1, 'Maintainer');
			$contributor = new ProjectRole(2, 'Contributor');

			return [
				$maintainer,
				$contributor,
				new ProjectMember(1, $johnDoe, $maintainer),
				new ProjectMember(2, $janeSmith, $maintainer),
				new ProjectMember(3, $johnDoe, $contributor),
			];
		});
		$queries = $this->createDoctrineQueries();
		$criteria = ['role.members.author.name' => 'Jane Smith'];

		$this->assertSame(
			[1, 2],
			$queries->scalars->findColumnValuesBy(ProjectMember::class, 'id', $criteria, self::OrderById)->asArray(),
			'The members of a role Jane Smith is a member of.',
		);
		$this->assertSame(2, $queries->countBy(ProjectMember::class, $criteria));
	}

	public function testManyToManyAssociation(): void
	{
		$this->persistFixtures(static function (): array {
			$php = new NoteTag(1, 'php');
			$doctrine = new NoteTag(2, 'doctrine');

			return [
				$php,
				$doctrine,
				new Note(1, 'First', [$php, $doctrine]),
				new Note(2, 'Second', [$php]),
				new Note(3, 'Third'),
			];
		});
		$queries = $this->createDoctrineQueries();

		$this->assertSame([1], $queries->scalars->findColumnValuesBy(Note::class, 'id', ['tags.name' => 'doctrine'])->asArray());
		$this->assertSame([1, 2], $queries->scalars->findColumnValuesBy(Note::class, 'id', ['tags.name' => 'php'], self::OrderById)->asArray());
		$this->assertSame([3], $queries->scalars->findColumnValuesBy(Note::class, 'id', ['tags.name' => null])->asArray());
		$this->assertSame(2, $queries->countBy(Note::class, ['tags.name' => ['php', 'doctrine']]));
		$this->assertSame(
			['php'],
			$queries->scalars->findColumnValuesBy(NoteTag::class, 'name', ['notes.title' => 'Second'])->asArray(),
			'The inverse side of the association.',
		);
	}

	public function testToManyCriteriaInSubQuery(): void
	{
		$queries = $this->createQueriesWithHashtags();

		$this->assertSame(
			[1],
			$queries->scalars->findColumnValuesBy(Author::class, 'id', [
				'id' => $queries->subQuery(Article::class, ['hashTags.hashtag' => 'doctrine'], select: ['author']),
			])->asArray(),
		);
	}

	private function createQueriesWithHashtags(): DoctrineQueries
	{
		$this->persistFixtures();

		$em = $this->getEntityManager();
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'php'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 1), 'doctrine'));
		$em->persist(new ArticleHashtag($em->getReference(Article::class, 2), 'php'));
		$em->flush();
		$em->clear();

		return $this->createDoctrineQueries();
	}

}
