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
- Doctrine ORM 3.2 or higher

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
```

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
// Not equals
['status !=' => 'inactive']

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

## Sorting

```php
$users = $queries->arrays->findBy(
    User::class,
    ['status' => 'active'],
    orderBy: ['created_at' => 'DESC', 'name' => 'ASC']
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
    select: ['**'] // Selects all fields including ManyToOne relations
)->asArray();
```

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
```

## PHPStan Integration

The library includes PHPStan extensions for static analysis. Add the extension to your `phpstan.neon`:

```yaml
includes:
    - vendor/shredio/doctrine-queries/extension.neon
```

This provides:
- Type inference for query results
- Validation of entity classes and field names
- Detection of invalid criteria and operators

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
    orderBy: ['created_at' => 'DESC'],
    select: ['id', 'name', 'email', 'created_at']
)->asArray();

// Get user count by status
$statusCounts = [];
foreach (['active', 'inactive', 'banned'] as $status) {
    $statusCounts[$status] = $queries->countBy(User::class, ['status' => $status]);
}

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
    orderBy: ['published_at' => 'DESC'],
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
    orderBy: ['price' => 'ASC'],
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
