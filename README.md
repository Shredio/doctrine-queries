# Doctrine Queries

A simplified and type-safe PHP library for Doctrine ORM that provides an intuitive interface for database queries with automatic query building, result handling, and PHPStan integration.

## Features

- **Simple and intuitive API** for common database operations
- **Type-safe queries** with full PHPStan support
- **Multiple result formats** - objects, arrays, or scalar values
- **Flexible criteria system** with support for operators, null values, and arrays
- **Automatic query optimization** and result hydration
- **Memory-efficient iteration** for large datasets
- **Built-in PHPStan extensions** for static analysis

## Requirements

- PHP 8.3 or higher
- Doctrine ORM 3.7 or higher with Doctrine DBAL 4
- `symfony/polyfill-php86` below PHP 8.6 (installed as a dependency), for the `SortDirection` enum

## Installation

```bash
composer require shredio/doctrine-queries
```

## Quick Start

```php
use Shredio\DoctrineQueries\DoctrineQueries;

// Initialize with Doctrine's ManagerRegistry
$queries = new DoctrineQueries($managerRegistry);

// Query entities as objects
$users = $queries->objects->findBy(User::class, ['status' => 'active'])->asArray();

// Query as associative arrays (faster hydration)
$userData = $queries->arrays->findBy(User::class, ['age >' => 18])->asArray();

// Query scalar values only
$userNames = $queries->scalars->findColumnValuesBy(User::class, 'name', ['status' => 'active'])->asArray();

// Use subqueries for complex filtering
$activeUsersWithPosts = $queries->objects->findBy(
    User::class,
    [
        'id' => $queries->subQuery(
            Post::class,
            ['status' => 'published'],
            select: ['author.id']
        )
    ]
)->asArray();

// Check if entities exist
$hasActiveUsers = $queries->existsBy(User::class, ['status' => 'active']);

// Count entities
$activeUserCount = $queries->countBy(User::class, ['status' => 'active']);

// Delete entities
$deletedCount = $queries->deleteBy(User::class, ['status' => 'inactive']);
```

## Query Types

### Object Queries

Returns full entity objects with all relationships loaded:

```php
$users = $queries->objects->findBy(User::class, ['status' => 'active'])->asArray();
// Returns: User[]
```

### Array Queries

Returns associative arrays (faster hydration, less memory usage):

```php
$users = $queries->arrays->findBy(User::class, ['status' => 'active'])->asArray();
// Returns: array<string, mixed>[]

// With field selection
$users = $queries->arrays->findBy(User::class, ['status' => 'active'], select: ['id', 'name'])->asArray();
// Returns: [['id' => 1, 'name' => 'John'], ...]

// With join configuration for optimal performance
$posts = $queries->arrays->findBy(
    Post::class, 
    ['status' => 'published'], 
    select: ['title', 'author.name'],
    joinConfig: ['author' => 'inner'] // Use inner join for better performance
)->asArray();
```

### Scalar Queries

Returns primitive values only:

```php
$userNames = $queries->scalars->findColumnValuesBy(User::class, 'name')->asArray();
// Returns: string[]

$userName = $queries->scalars->findSingleColumnValueBy(User::class, 'name', ['id' => 1]);
// Returns: string|null

// The ordering decides which row is the first one
$latestLogin = $queries->scalars->findSingleColumnValueBy(User::class, 'lastLoginAt', ['status' => 'active'], orderBy: ['lastLoginAt' => SortDirection::Descending]);
```

`findSingleColumnValueBy()` returns `null` both when no row matches and when the value is `NULL`. To tell them apart, use `findOneBy($entity, $criteria, select: [$field])`, which returns `null` for no row and `[$field => null]` for a `NULL` value.

## Criteria System

The library supports a flexible criteria system with various operators:

### Basic Equality

```php
// Equals
['name' => 'John']
['id' => 42]

// Explicit equals
['name =' => 'John']
```

### Comparison Operators

```php
// Not equals (`<>` works the same)
['status !=' => 'inactive']
['status <>' => 'inactive']

// Greater than
['age >' => 18]

// Greater than or equal
['age >=' => 18]

// Less than
['score <' => 100]

// Less than or equal
['score <=' => 100]
```

### Pattern Matching

```php
// LIKE pattern matching
['name LIKE' => '%john%']

// NOT LIKE
['email NOT LIKE' => '%@spam.com']
```

Supported operators are `=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `LIKE` and `NOT LIKE`, case-insensitive. Any other operator throws an `InvalidArgumentException`, so a criteria key never reaches DQL unchecked. Fields are validated against the entity mapping as well.

### Null Handling

```php
// IS NULL
['deleted_at' => null]

// IS NOT NULL
['deleted_at !=' => null]
```

### Array Values (IN/NOT IN)

```php
// IN clause
['status' => ['active', 'pending']]
['id' => [1, 2, 3]]

// NOT IN clause
['status !=' => ['banned', 'deleted']]

// An empty list matches nothing with IN and everything with NOT IN
['id' => []]  // no rows
['id !=' => []]  // all rows

// Objects in a list are converted by the type of the column, e.g. dates or the value objects of a custom DBAL type
['createdAt' => [new DateTimeImmutable('2024-01-01'), new DateTimeImmutable('2024-02-01')]]
```

### Value Objects and Entities

```php
// A value object of a custom DBAL type is converted by the type of the column, alone or in a list
['id' => $portfolioId]
['id' => [$firstPortfolioId, $secondPortfolioId]]

// An association is compared by the identifier of its entity: pass the entity, its identifier object or the database value
['portfolio' => $portfolio]
['portfolio' => [$portfolio, $anotherPortfolioId]]
```

The values of a binary column (e.g. a UUID stored in `BINARY(16)`) are bound as binary, also the raw bytes passed as a string. A value implementing `DoctrineParameterAware` creates its own parameter when it is passed alone. A date compared with a column of a date type is converted by the type of the column, alone or in a list. A `DATE` column is so compared with the date only, `['day >=' => new DateTimeImmutable('2026-01-01 10:00:00')]` matches the rows of 2026-01-01 too, and a `TIME` column with the time only.

SQL never matches `NULL` by a comparison, so `['status !=' => 'banned']` skips rows whose status is `NULL`. A `null` in a list is compared by `IS NULL` instead: `['status' => ['active', null]]` matches active rows and rows without a status, `['status !=' => ['banned', null]]` matches rows with a status other than banned.

### Criteria on To-Many Associations

A criteria reached through a to-many association (one-to-many or many-to-many) filters the root entities by an `EXISTS` sub-query, it does not join the association. Each entity is returned once, a limit counts the entities and the results can be iterated by `yield()`:

```php
// Posts with a comment by John, each post once
$posts = $queries->arrays->findBy(Post::class, ['comments.author.name' => 'John'])->asArray();

// All criteria of one association have to match the same related entity
$posts = $queries->arrays->findBy(Post::class, ['comments.author.name' => 'John', 'comments.approved' => true])->asArray();

// Posts without any comment, as a left join of no comment would match
$posts = $queries->arrays->findBy(Post::class, ['comments.id' => null])->asArray();
```

When the selection or the ordering uses a field of the association too, the association is joined and the criteria filter its rows, a row per matching related entity:

```php
// Only the approved comments of each post
$comments = $queries->scalars->findBy(Post::class, ['comments.approved' => true], select: ['id', 'comments.content'])->asArray();
```

### Multiple Criteria (AND)

```php
$criteria = [
    'age >' => 18,
    'status' => 'active',
    'city' => ['Prague', 'Brno'],
    'deleted_at' => null
];
```

### Alternatives (OR)

`Criteria::anyOf()` matches the rows that match at least one of its alternatives. The criteria of one alternative all have to match, as the criteria of a query do. The group is passed among the criteria without a key:

```php
use Shredio\DoctrineQueries\Criteria\Criteria;

// published AND (author.name = 'John' OR (title LIKE '%John%' AND featured))
$criteria = [
    'published' => true,
    Criteria::anyOf(
        ['author.name' => 'John'],
        ['title LIKE' => '%John%', 'featured' => true],
    ),
];

// More groups are combined by AND, an alternative can contain another group
$criteria = [
    Criteria::anyOf(['status' => 'active'], ['role' => 'admin']),
    Criteria::anyOf(
        ['age >=' => 18],
        ['verified' => true, Criteria::anyOf(['city' => 'Prague'], ['city' => 'Brno'])],
    ),
];
```

Alternatives of a single field are shorter as a list, `['status' => ['active', 'pending']]`.

- `Criteria::anyOf()` without an alternative and an alternative without criteria throw an `InvalidArgumentException`. An alternative that matches every row, e.g. `['id !=' => []]`, makes the whole group match every row.
- A to-many association is checked by an `EXISTS` sub-query of each alternative, so the related entity is not shared with the other alternatives or with the criteria around the group: `['comments.approved' => true, Criteria::anyOf(['comments.author.name' => 'John'], ['pinned' => true])]` matches a post with an approved comment and another comment by John. Use a [sub-query](#subqueries) on the related entity when one related entity has to match all of it.
- Relations are left joined by default, so a row without the relation can match by another alternative. An inner join (`joinConfig`) requires the relation of every row.
- `deleteBy()` accepts groups too, their alternatives cannot use related entities either.

## Sorting

Directions are `SortDirection` cases (native since PHP 8.6, polyfilled below it), the same enum Doctrine ORM 3.7 expects instead of the deprecated `'ASC'` / `'DESC'` strings.

```php
$users = $queries->arrays->findBy(
    User::class,
    ['status' => 'active'],
    orderBy: ['created_at' => SortDirection::Descending, 'name' => SortDirection::Ascending]
)->asArray();
```

Wrap a direction in `NullsLast` to put `NULL` values after all others, whichever the direction and however the database platform sorts `NULL`:

```php
use Shredio\DoctrineQueries\Order\NullsLast;

$users = $queries->arrays->findBy(
    User::class,
    orderBy: ['last_login_at' => new NullsLast(SortDirection::Descending), 'id' => SortDirection::Ascending]
)->asArray();
```

## Field Selection

```php
// Select specific fields
$users = $queries->arrays->findBy(
    User::class,
    ['status' => 'active'],
    select: ['id', 'name', 'email']
)->asArray();

// Field aliasing
$users = $queries->arrays->findBy(
    User::class,
    ['status' => 'active'],
    select: ['id' => 'userId', 'name' => 'fullName']
)->asArray();

// Select related fields with automatic joins
$posts = $queries->arrays->findBy(
    Post::class,
    ['status' => 'published'],
    select: ['title', 'author.name', 'category.name']
)->asArray();

// Select all fields (without relations)
$posts = $queries->arrays->findBy(
    Post::class,
    ['status' => 'published'],
    select: ['*'] // Selects all fields
)->asArray();

// Select all fields including relations
$posts = $queries->arrays->findBy(
    Post::class,
    ['status' => 'published'],
    select: ['**'] // Selects all fields and the identifiers of ManyToOne and owning OneToOne relations
)->asArray();
```

Without `select` the result contains the fields of the entity, the same as `select: ['*']`.

An alias (or the prefix of a wildcard) is written to the DQL as it is, so it has to be an identifier: letters, digits and underscores, not starting with a digit. Any other alias throws an `InvalidSelectAliasException`.

Selecting a field of a to-many association returns a row per related entity. Doctrine refuses to iterate a query joining a to-many association, so `yield()` of such a query loads all rows at once.

## Working with Results

### Arrays

```php
$users = $queries->arrays->findBy(User::class)->asArray();
foreach ($users as $user) {
    echo $user['name'];
}
```

### Memory-Efficient Iteration

For large datasets, use yielding to avoid loading everything into memory:

```php
foreach ($queries->arrays->findBy(User::class)->yield() as $user) {
    // Process one user at a time
    processUser($user);
}
```

### Key-Value Pairs

```php
// Get id => name pairs
$userOptions = $queries->arrays->findPairsBy(User::class, 'id', 'name')->asArray();
// Returns: [1 => 'John', 2 => 'Jane', ...]
```

PHP arrays accept only integers and strings as keys, so `asArray()` of `findPairsBy()`, `findIndexedBy()` and the grouped aggregations (`countGroupedBy()`, `sumGroupedBy()`, `minGroupedBy()`, `maxGroupedBy()`) throws an `InvalidResultKeyException` for a row whose key is `NULL` or an object (e.g. an enum or a date hydrated by `$queries->arrays`). Exclude `NULL` keys with the criteria, use `$queries->scalars` for the database value of an object, or iterate over `yield()`, which returns any key as it is:

```php
// Roles are optional, authors without one would have a NULL key
$authorsByRole = $queries->scalars->findPairsBy(Author::class, 'role.id', 'name', ['role.id !=' => null])->asArray();
```

Methods reading a single field (`findColumnValuesBy()`, `findSingleColumnValueBy()`, the key and value of `findPairsBy()`, the index field of `findIndexedBy()`, the field of `countBy()` and the group and the field of `countGroupedBy()` and of the other aggregations) throw a `MultipleFieldSelectionException` for a wildcard such as `'*'` or `'author.**'`.

### Column Values

```php
// Get all unique email domains
$domains = $queries->scalars->findColumnValuesBy(
    User::class, 
    'email_domain', 
    distinct: true
)->asArray();
```

## Advanced Features

### Subqueries

Use subqueries for complex filtering and data retrieval:

```php
// Find users who have published posts
$usersWithPosts = $queries->objects->findBy(
    User::class,
    [
        'id' => $queries->subQuery(
            Post::class,
            ['status' => 'published'],
            select: ['author.id']
        )
    ]
)->asArray();

// Find posts by users from specific cities
$posts = $queries->arrays->findBy(
    Post::class,
    [
        'author.id' => $queries->subQuery(
            User::class,
            ['city' => ['Prague', 'Brno']],
            select: ['id']
        )
    ]
)->asArray();
```

A sub-query is compared with a single value, so it has to select exactly one column (the entity itself without `select`), otherwise a `LogicException` is thrown.

### Join Configuration

Control join types for optimal query performance:

```php
// Use inner joins for better performance when you know relations exist
$posts = $queries->arrays->findBy(
    Post::class,
    ['author.status' => 'active'],
    select: ['title', 'author.name'],
    joinConfig: [
        'author' => 'inner' // Inner join for author relation
    ]
)->asArray();

// Mix of inner and left joins
$comments = $queries->arrays->findBy(
    Comment::class,
    [],
    select: ['content', 'author.name', 'post.title'],
    joinConfig: [
        'author' => 'left',  // Left join (author might be null)
        'post' => 'inner'    // Inner join (comment must have a post)
    ]
)->asArray();
```

Only `'left'` and `'inner'` are accepted, any other join type throws an `InvalidArgumentException`.

## Counting and Existence

```php
// Count entities
$activeUsers = $queries->countBy(User::class, ['status' => 'active']);

// Check existence
$hasAdmins = $queries->existsBy(User::class, ['role' => 'admin']);

// Count with complex criteria
$recentActiveUsers = $queries->countBy(User::class, [
    'status' => 'active',
    'last_login >' => new DateTime('-30 days')
]);

// Count specific field values
$uniqueEmails = $queries->countBy(User::class, [], field: 'email', distinct: true);

// Count with join configuration
$postsWithActiveAuthors = $queries->countBy(
    Post::class,
    ['author.status' => 'active'],
    joinConfig: ['author' => 'left']
);
```

### Counts per Value

`countGroupedBy()` counts the entities for each value of a field or a to-one association in a single query, instead of one `countBy()` per value:

```php
// [commentId => number of replies]
$replyCounts = $queries->countGroupedBy(Comment::class, 'parent', ['parent' => $commentIds])->asArray();

// A comment without replies is missing in the result, it is not counted as 0
$replyCount = $replyCounts[$commentId] ?? 0;

// By a field of a related entity, with the same options as countBy()
$postsByAuthorStatus = $queries->countGroupedBy(Post::class, 'author.status', ['published' => true], joinConfig: 'inner')->asArray();

// Count the values of a field (without NULL) instead of the entities, such a count can be 0
$publishedPostsByAuthor = $queries->countGroupedBy(Post::class, 'author', field: 'publishedAt')->asArray();
```

The keys are database values as in `$queries->scalars` (the identifier for an association), so a date or an enum can be a key. The groups are returned in no particular order. A `NULL` key is handled as in [Key-Value Pairs](#key-value-pairs): exclude it with the criteria, e.g. `['parent !=' => null]`, or iterate over `yield()`.

### Sum, Minimum and Maximum

```php
// Sum of a field, null when no entity matches or all the values are NULL
$totalViews = $queries->sumBy(Article::class, 'views', ['author' => $authorId]) ?? 0;

// Lowest and highest value, NULL values are skipped
$firstPublishedAt = $queries->minBy(Article::class, 'publishedAt', ['author' => $authorId]);
$lastPosition = $queries->maxBy(Ticker::class, 'position');

// For each value of a field or a to-one association, as countGroupedBy()
$viewsByAuthor = $queries->sumGroupedBy(Article::class, 'views', 'author', ['author' => $authorIds])->asArray();
$lastPublishedAtByAuthor = $queries->maxGroupedBy(Article::class, 'publishedAt', 'author')->asArray();
```

The databases return aggregations in types of their own, e.g. MySQL sums integers to a decimal string and Doctrine does not convert the result of a function. The methods return the same types on every database:

- `sumBy()` returns an integer for an integer field, a numeric string for a decimal field and a float for a float field. Other fields cannot be summed, unless their custom type is summable, see below. A sum of an integer field that does not fit into a PHP integer throws an `AggregationOverflowException`.
- `minBy()` and `maxBy()` return a value of the field converted by its type, as `$queries->arrays` returns a selected field: a date object for a date, an enum for an enum field.

A field of a related entity can be aggregated too (`'author.rating'`, `'comments.likes'`), an association itself cannot. The keys of the grouped variants are database values and a value no entity matches is missing in the result, as in `countGroupedBy()`. The aggregation of a group is `null` only when all its values are `NULL`.

A field of a custom DBAL type can be summed when the type implements `SummableType`. The sum is then converted by `convertToPHPValue()` of the type, as a value of the field is, so it has the PHP type of the field:

```php
use Shredio\DoctrineQueries\Aggregation\SummableType;

// A decimal column read as a float, its sum is a float too
final class FloatDecimalType extends Type implements SummableType
{

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?float
    {
        return $value === null ? null : (float) $value; // a sum is a numeric string, an integer or a float
    }

    // ...
}

$costByProvider = $queries->sumGroupedBy(AiCall::class, 'costUsd', 'provider')->asArray(); // array<string, float>
```

The PHPStan extension infers the type of such a sum from the descriptor of the type, as it does for a selected field.

## Deletion Operations

```php
// Delete entities by criteria
$deletedCount = $queries->deleteBy(User::class, ['status' => 'inactive']);

// Delete with complex criteria
$deletedOldUsers = $queries->deleteBy(User::class, [
    'last_login <' => new DateTime('-1 year'),
    'status' => 'inactive'
]);

// Delete all entities (use with caution)
$deletedAll = $queries->deleteBy(User::class);

// DQL DELETE cannot join, so criteria on related entities ('author.name') throw a LogicException.
// Filter by the association with a sub-query instead:
$deletedPosts = $queries->deleteBy(Post::class, [
    'author' => $queries->subQuery(User::class, ['status' => 'banned']),
]);
```

## PHPStan Integration

The library includes PHPStan extensions for static analysis. Add the extension to your `phpstan.neon`:

```yaml
includes:
    - vendor/shredio/doctrine-queries/extension.neon
```

This provides:
- Type inference for query results
- Validation of entity classes and field names in criteria, ordering, selection, join configuration and preloading
- Detection of unsupported criteria operators and of criteria on related entities in `deleteBy()`
- The same validation of the alternatives of `Criteria::anyOf()`, and detection of an alternative without criteria. The alternatives have to be constant arrays, as the criteria do. They do not narrow the inferred types, a row can match by any of them
- Detection of options passed as positional arguments (`orderBy`, `select`, `pagination`, `joinConfig`)
- Detection of wildcards passed where a single field is expected, e.g. `findColumnValuesBy(User::class, '*')`
- Detection of sub-queries selecting more than one column
- Detection of sums of fields that are not numbers and of aggregated associations, e.g. `sumBy(User::class, 'name')`
- Detection of an object in the criteria that the type of the compared column does not convert, e.g. a value object of another type than the custom DBAL type of the column takes, alone or in a list. Such a call throws when the query runs. The converted type is the one the phpstan-doctrine descriptor of the column type writes to the database, so describe your custom types precisely. Only a value that certainly throws is reported: scalars, values of an unknown type and objects a type passes through (a `Stringable` compared with a string column) are not
- Detection of `asArray()` of `findPairsBy()`, `findIndexedBy()` and the grouped aggregations (e.g. `countGroupedBy()`) whose key can be `NULL` or is not an integer or a string, see [Key-Value Pairs](#key-value-pairs). Only calls chained directly on the finder are checked, a result stored in a variable first is not followed.

Doctrine does not convert scalar results (`$queries->scalars`, and `IDENTITY()` of associations in `$queries->arrays`), so their PHP types depend on the database driver. The extension infers them for the driver of the connection returned by phpstan-doctrine's `objectManagerLoader`, including `PDO::ATTR_STRINGIFY_FETCHES`. For example a `decimal` column is `float|int` on `pdo_sqlite` but `numeric-string` on `pdo_mysql` and `mysqli`, so configure the loader with the same driver and options as the application. Criteria narrow the inferred type only when the database returns the value in the form it was compared in, e.g. `['name' => 'John']` does not narrow `name` to `'John'` on MySQL, whose default collations compare case-insensitively.

The integers of an integer column mapped as unsigned (`options: ['unsigned' => true]`) are inferred as `int<0, max>` wherever the column is returned: a selected field, a key, `sumBy()`, `minBy()`, `maxBy()` and their grouped variants. Only the mapping is read, not the PHPDoc of the property, and the database is trusted to follow it: MySQL and MariaDB store no negative number in such a column, the databases without unsigned columns (PostgreSQL, SQLite) do not check it.

The inferred types are narrowed only by what every returned row certainly matches:

- `['id' => 1]` narrows `id` to `1`, `['id !=' => 1]` excludes `1`. A value that is not known exactly excludes nothing, `['id !=' => $id]` keeps `int`.
- Every comparison except with `null` excludes `NULL`: `['price >' => 5]`, `['status !=' => 'banned']` and `['name LIKE' => 'J%']` make a nullable field non-nullable.
- A case of an enum narrows the field to the case, `['type' => ArticleType::Blog]`, or to its value in `$queries->scalars`.
- Criteria built by conditions narrow by the criteria each variant has: an item added in an `if` does not narrow, `$published ? ['id' => 1] : ['id' => 2]` narrows `id` to `1|2`.
- A selection, a field or a join configuration built by conditions gives the type of any of the queries that can run, e.g. `array{id: int, title?: string}`. A join that is not certainly an inner one is taken as a left join. When the selection or the entity is not known (`select: $fields`, unpacked arguments), the result has the declared type.

## Testing

The tests run against in-memory SQLite. To run them against MySQL 9.7, start the server from `compose.yaml` and point `DATABASE_URL` to it:

```bash
docker compose up -d --wait
DATABASE_URL="pdo-mysql://root:root@127.0.0.1:3307/doctrine_queries" composer test
DATABASE_URL="mysqli://root:root@127.0.0.1:3307/doctrine_queries" composer test
```

`tests/PhpStan/RuntimeTypes` compares the types inferred by the PHPStan extension with the values the driver really returns, it is worth running for any driver configuration, e.g. with stringified fetches (`driverOptions[17]` is `PDO::ATTR_STRINGIFY_FETCHES`):

```bash
DATABASE_URL="pdo-mysql://root:root@127.0.0.1:3307/doctrine_queries?driverOptions[17]=1" vendor/bin/phpunit tests/PhpStan/RuntimeTypes
```

Mutation testing of the query building and result classes: `composer infection`.

## Performance Tips

1. **Use array queries** for better performance when you don't need full entity objects
2. **Use scalar queries** when you only need specific field values
3. **Use yielding** for large datasets to avoid memory issues
4. **Select only needed fields** to reduce data transfer
5. **Use distinct** when appropriate to reduce result size
6. **Configure joins properly** - use inner joins when relations are guaranteed to exist
7. **Use subqueries** for complex filtering instead of loading large datasets in PHP
8. **Leverage field path selection** for automatic join optimization

## Examples

### User Management

```php
// Get active users with their profiles
$users = $queries->arrays->findBy(
    User::class,
    ['status' => 'active'],
    orderBy: ['created_at' => SortDirection::Descending],
    select: ['id', 'name', 'email', 'created_at']
)->asArray();

// Get user count by status in a single query, a status without users is missing
$statusCounts = $queries->countGroupedBy(User::class, 'status')->asArray();

// Get recent user emails for newsletter
$recentEmails = $queries->scalars->findColumnValuesBy(
    User::class,
    'email',
    ['created_at >' => new DateTime('-7 days')]
)->asArray();
```

### Content Management

```php
// Get published articles with author info using modern field path selection
$articles = $queries->arrays->findBy(
    Article::class,
    [
        'status' => 'published',
        'published_at <=' => new DateTime()
    ],
    orderBy: ['published_at' => SortDirection::Descending],
    select: ['title', 'author.name', 'published_at'],
    joinConfig: ['author' => 'inner'] // Ensure articles have authors
)->asArray();

// Get article titles for search suggestions
$suggestions = $queries->scalars->findColumnValuesBy(
    Article::class,
    'title',
    ['status' => 'published', 'title LIKE' => '%' . $query . '%'],
    distinct: true
)->asArray();

// Find articles by users with specific roles using subqueries
$adminArticles = $queries->arrays->findBy(
    Article::class,
    [
        'author.id' => $queries->subQuery(
            User::class,
            ['role' => 'admin'],
            select: ['id']
        )
    ],
    select: ['title', 'author.name']
)->asArray();
```

### E-commerce

```php
// Get products in stock with pricing and category info
$products = $queries->arrays->findBy(
    Product::class,
    [
        'stock_quantity >' => 0,
        'status' => 'active',
        'category.name' => ['Electronics', 'Books']
    ],
    orderBy: ['price' => SortDirection::Ascending],
    select: ['id', 'name', 'price', 'stock_quantity', 'category.name'],
    joinConfig: ['category' => 'inner']
)->asArray();

// Get products with recent orders using subqueries
$popularProducts = $queries->arrays->findBy(
    Product::class,
    [
        'id' => $queries->subQuery(
            OrderItem::class,
            ['order.created_at >' => new DateTime('-30 days')],
            select: ['product.id']
        )
    ],
    select: ['name', 'price']
)->asArray();

// Get order statistics
$orderStats = [
    'total' => $queries->countBy(Order::class),
    'pending' => $queries->countBy(Order::class, ['status' => 'pending']),
    'completed' => $queries->countBy(Order::class, ['status' => 'completed'])
];
```

## Testing

```bash
composer test
```

## Static Analysis

```bash
composer phpstan
```

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## Changelog

See [releases](https://github.com/shredio/doctrine-queries/releases) for version history and changes.
