<?php declare(strict_types = 1);

namespace Tests\Unit\Query;

use Doctrine\ORM\Mapping\MappingException;
use PHPUnit\Framework\Attributes\DataProvider;
use Shredio\DoctrineQueries\Pagination\Pagination;
use Shredio\DoctrineQueries\Query\ObjectQueries;
use SortDirection;
use Tests\Context\DoctrineContext;
use Tests\Entity\Article;
use Tests\Entity\Author;
use Tests\Entity\ProjectMember;
use Tests\Entity\ProjectRole;
use Tests\Entity\Role;
use Tests\TestCase;

final class ObjectQueriesTest extends TestCase
{

	use DoctrineContext;

	private const array ByIdAscending = ['id' => SortDirection::Ascending];

	public function testFindBy(): void
	{
		$this->persistFixtures();

		$values = $this->assertSingleQuery(fn (): array => $this->getQueries()->findBy(Article::class, orderBy: self::ByIdAscending)->asArray());

		$this->assertContainsOnlyInstancesOf(Article::class, $values);
		$this->assertSame([1, 2, 3], self::ids($values));
	}

	public function testFindByYield(): void
	{
		$this->persistFixtures();

		$values = $this->assertSingleQuery(fn (): array => iterator_to_array($this->getQueries()->findBy(Article::class, orderBy: self::ByIdAscending)->yield(), false));

		$this->assertSame([1, 2, 3], self::ids($values));
	}

	public function testFindByPagination(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$this->assertSame([2, 3], self::ids($queries->findBy(Article::class, orderBy: self::ByIdAscending, pagination: new Pagination(2, 1))->asArray()));
		$this->assertSame([3], self::ids($queries->findBy(Article::class, orderBy: self::ByIdAscending)->setOffset(2)->asArray()));
	}

	public function testFindByJoins(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$this->assertSame([2], self::ids($this->assertSingleQuery(fn (): array => $queries->findBy(Article::class, ['author.name' => 'Jane Smith'])->asArray())));
		$this->assertSame([1, 3], self::ids($queries->findBy(Article::class, ['author.role.id' => null], self::ByIdAscending, joinConfig: ['author.role' => 'left'])->asArray()));
		$this->assertSame([2], self::ids($queries->findBy(Article::class, ['author.role.id' => 1], joinConfig: 'inner')->asArray()));
		$this->assertSame([2], self::ids($queries->findBy(Article::class, ['author.role.id' => 1], joinConfig: ['author.role' => 'inner'])->asArray()));
	}

	public function testFindOneBy(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$this->assertSame(1, $this->assertSingleQuery(fn (): ?Article => $queries->findOneBy(Article::class, orderBy: self::ByIdAscending))?->getId());
		$this->assertSame(3, $queries->findOneBy(Article::class, orderBy: ['id' => SortDirection::Descending])?->getId());
		$this->assertNull($this->assertSingleQuery(fn (): ?Article => $queries->findOneBy(Article::class, ['author' => 999])));
	}

	public function testFindOneByJoins(): void
	{
		$this->persistFixtures();
		$queries = $this->getQueries();

		$this->assertSame(2, $this->assertSingleQuery(fn (): ?Article => $queries->findOneBy(Article::class, ['author.name' => 'Jane Smith']))?->getId());
		$this->assertSame(1, $queries->findOneBy(Article::class, ['author.role.id' => null], self::ByIdAscending, ['author.role' => 'left'])?->getId());
		$this->assertSame(2, $queries->findOneBy(Article::class, ['author.role.id' => 1], [], 'inner')?->getId());
		$this->assertNull($queries->findOneBy(Article::class, ['author.role.id' => null], [], 'inner'));
	}

	public function testAssociationsAreLoadedLazilyWithoutPreload(): void
	{
		$this->persistFixtures();

		$authorNames = $this->assertQueryCount(3, function (): array {
			$values = $this->getQueries()->findBy(Article::class, orderBy: self::ByIdAscending)->asArray();

			return array_map(static fn (Article $article): string => $article->getAuthor()->getName(), $values);
		}, 'Expected one query for articles and one query for each of the two authors.');

		$this->assertSame(['John Doe', 'Jane Smith', 'John Doe'], $authorNames);
	}

	public function testPreload(): void
	{
		$this->persistFixtures();

		$articles = $this->assertQueryCount(
			2,
			fn (): array => $this->getQueries()->findBy(Article::class, orderBy: self::ByIdAscending, preload: ['author'])->asArray(),
			'Expected one query for articles and one query for authors.',
		);
		$authorNames = $this->assertQueryCount(
			0,
			static fn (): array => array_map(static fn (Article $article): string => $article->getAuthor()->getName(), $articles),
			'The preloaded authors are not loaded lazily.',
		);

		$this->assertSame(['John Doe', 'Jane Smith', 'John Doe'], $authorNames);
	}

	public function testPreloadWithYield(): void
	{
		$this->persistFixtures();

		$authorNames = $this->assertQueryCount(2, function (): array {
			$names = [];
			foreach ($this->getQueries()->findBy(Article::class, orderBy: self::ByIdAscending, preload: ['author'])->yield() as $article) {
				$names[] = $article->getAuthor()->getName();
			}

			return $names;
		});

		$this->assertSame(['John Doe', 'Jane Smith', 'John Doe'], $authorNames);
	}

	/**
	 * @return iterable<string, array{list<string>}>
	 */
	public static function provideNestedPreload(): iterable
	{
		yield 'nested path' => [['author.role']];
		yield 'parent and nested path' => [['author', 'author.role']];
		yield 'nested path before its parent' => [['author.role', 'author']];
	}

	/**
	 * @param list<string> $preload
	 */
	#[DataProvider('provideNestedPreload')]
	public function testNestedPreload(array $preload): void
	{
		// Two roles, so loading them lazily would take a query for each and could not be mistaken for the preload.
		$this->persistFixtures(static function (): array {
			$editor = new Role(2, 'Editor');
			$editorAuthor = new Author(3, 'Emma Jones', $editor);

			return [$editor, $editorAuthor, new Article(4, 'Fourth Article', 'This is the fourth article.', $editorAuthor)];
		});

		$articles = $this->assertQueryCount(
			3,
			fn (): array => $this->getQueries()->findBy(Article::class, orderBy: self::ByIdAscending, preload: $preload)->asArray(),
			'Expected one query for articles, one for authors and one for roles.',
		);
		$roleNames = $this->assertQueryCount(
			0,
			static fn (): array => array_map(static fn (Article $article): ?string => $article->getAuthor()->getRole()?->getName(), $articles),
			'The preloaded authors and roles are not loaded lazily.',
		);

		$this->assertSame([null, 'Administrator', null, 'Editor'], $roleNames);
	}

	public function testPreloadOfMultipleAssociations(): void
	{
		$this->persistProjectMembers();

		$members = $this->assertQueryCount(
			3,
			fn (): array => $this->getQueries()->findBy(ProjectMember::class, orderBy: self::ByIdAscending, preload: ['author', 'role'])->asArray(),
			'Expected one query for members, one for authors and one for project roles.',
		);
		$descriptions = $this->assertQueryCount(
			0,
			static fn (): array => array_map(
				static fn (ProjectMember $member): string => sprintf('%s (%s)', $member->getAuthor()->getName(), $member->getRole()->getLabel()),
				$members,
			),
			'Both preloaded associations are not loaded lazily.',
		);

		$this->assertSame(['John Doe (Maintainer)', 'Jane Smith (Maintainer)', 'Jane Smith (Contributor)'], $descriptions);
	}

	public function testPreloadOfToManyAssociation(): void
	{
		$this->persistProjectMembers();

		$roles = $this->assertQueryCount(
			2,
			fn (): array => $this->getQueries()->findBy(ProjectRole::class, orderBy: self::ByIdAscending, preload: ['members'])->asArray(),
			'Expected one query for project roles and one for their members.',
		);
		$memberIds = $this->assertQueryCount(
			0,
			static fn (): array => array_map(
				static fn (ProjectRole $role): array => array_map(static fn (ProjectMember $member): int => $member->getId(), $role->getMembers()->getValues()),
				$roles,
			),
			'The preloaded collections are not loaded lazily.',
		);

		$this->assertEqualsCanonicalizing([1, 2], $memberIds[0]);
		$this->assertSame([3], $memberIds[1]);
	}

	public function testNestedPreloadThroughToManyAssociation(): void
	{
		$this->persistProjectMembers();

		$roles = $this->assertQueryCount(
			3,
			fn (): array => $this->getQueries()->findBy(ProjectRole::class, orderBy: self::ByIdAscending, preload: ['members.author'])->asArray(),
			'Expected one query for project roles, one for their members and one for the authors.',
		);
		$authorNames = $this->assertQueryCount(
			0,
			static fn (): array => array_map(
				static fn (ProjectRole $role): array => array_map(static fn (ProjectMember $member): string => $member->getAuthor()->getName(), $role->getMembers()->getValues()),
				$roles,
			),
			'The preloaded members and authors are not loaded lazily.',
		);

		$this->assertEqualsCanonicalizing(['John Doe', 'Jane Smith'], $authorNames[0]);
		$this->assertSame(['Jane Smith'], $authorNames[1]);
	}

	public function testPreloadWithoutResults(): void
	{
		$this->persistFixtures();

		$values = $this->assertSingleQuery(fn (): array => $this->getQueries()->findBy(Article::class, ['id' => 999], preload: ['author.role'])->asArray());

		$this->assertSame([], $values);
	}

	public function testPreloadOfUnknownField(): void
	{
		$this->persistFixtures();

		$this->expectException(MappingException::class);

		$this->getQueries()->findBy(Article::class, preload: ['nonExistent'])->asArray();
	}

	public function testPreloadOfFieldThatIsNotAnAssociation(): void
	{
		$this->persistFixtures();

		$this->expectException(MappingException::class);

		$this->getQueries()->findBy(Article::class, preload: ['title'])->asArray();
	}

	/**
	 * @param list<Article> $articles
	 * @return list<int>
	 */
	private static function ids(array $articles): array
	{
		return array_map(static fn (Article $article): int => $article->getId(), $articles);
	}

	/**
	 * | ProjectMember | author         | role            |
	 * |---------------|----------------|-----------------|
	 * | 1             | 1 (John Doe)   | 1 (Maintainer)  |
	 * | 2             | 2 (Jane Smith) | 1 (Maintainer)  |
	 * | 3             | 2 (Jane Smith) | 2 (Contributor) |
	 */
	private function persistProjectMembers(): void
	{
		$this->persistFixtures(static function (Author $johnDoe, Role $administrator, Author $janeSmith): array {
			$maintainer = new ProjectRole(1, 'Maintainer');
			$contributor = new ProjectRole(2, 'Contributor');

			return [
				$maintainer,
				$contributor,
				new ProjectMember(1, $johnDoe, $maintainer),
				new ProjectMember(2, $janeSmith, $maintainer),
				new ProjectMember(3, $janeSmith, $contributor),
			];
		});
	}

	private function getQueries(): ObjectQueries
	{
		return new ObjectQueries($this->createQueryBuilderFactory());
	}

}
