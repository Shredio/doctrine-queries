# Doctrine Queries - AI Assistant Guide

Type-safe Doctrine ORM query library with intuitive criteria system.

## Core Classes

- `DoctrineQueries` - Main entry point
- `ObjectQueries` - Returns entity objects  
- `ArrayQueries` - Returns associative arrays
- `ScalarQueries` - Returns primitive values only

## Basic Usage

```php
$queries = new DoctrineQueries($managerRegistry);

// Objects (full entities)
$users = $queries->objects->findBy(User::class, ['status' => 'active'])->asArray();

// Arrays (faster hydration)
$users = $queries->arrays->findBy(User::class, ['status' => 'active'])->asArray();

// Scalars (primitive values only)
$names = $queries->scalars->findColumnValuesBy(User::class, 'name')->asArray();
```

## Criteria System

```php
// Equals
['name' => 'John', 'id' => 42]

// Operators (only =, !=, <>, <, <=, >, >=, LIKE, NOT LIKE - others throw)
['age >' => 18, 'status !=' => 'banned', 'score <=' => 100]

// LIKE patterns
['name LIKE' => '%john%', 'email NOT LIKE' => '%spam%']

// Null handling
['deleted_at' => null, 'active !=' => null]

// Arrays (IN/NOT IN), objects in a list (dates, custom DBAL type values) are converted by the column type
['status' => ['active', 'pending'], 'id !=' => [1, 2, 3]]

// A null in a list is compared by IS NULL: active or no status / a status other than banned
['status' => ['active', null], 'role !=' => ['banned', null]]

// Through a to-many association: EXISTS sub-query, each root entity once, all criteria of the association match one related entity
['comments.author.name' => 'John', 'comments.approved' => true]
// Joined (a row per related entity) only when select or orderBy uses the association too

// Multiple criteria (AND)
['age >' => 18, 'status' => 'active', 'city' => ['Prague', 'Brno']]

// Alternatives (OR): passed without a key, the criteria of one alternative are combined by AND, groups can be nested
// published AND (author.name = John OR (title LIKE ... AND featured))
['published' => true, Criteria::anyOf(['author.name' => 'John'], ['title LIKE' => '%John%', 'featured' => true])]
// No alternative or an alternative without criteria throws, a to-many association is checked by an EXISTS of each alternative,
// PHPStan validates the alternatives (constant arrays only) and does not narrow the result types by them
```

## Field Selection

```php
// Specific fields
select: ['id', 'name', 'email']

// With aliases (identifiers only, otherwise InvalidSelectAliasException)
select: ['id' => 'userId', 'name' => 'fullName']

// Related fields (auto-joins)
select: ['title', 'author.name', 'category.name']

// Wildcards
select: ['*']        // All entity fields (no relations)
select: ['**']       // All fields + relation IDs (ManyToOne and owning OneToOne)
select: ['author.*'] // All author fields
select: ['author.**'] // All author fields + its relations
select: ['*', 'author.**' => 'author_'] // With prefix
```

## Join Configuration

```php
joinConfig: 'left'                    // LEFT JOIN all (default)
joinConfig: 'inner'                   // INNER JOIN all  
joinConfig: ['author' => 'inner']     // INNER JOIN specific relation
joinConfig: ['author.role' => 'left'] // LEFT JOIN nested relation
```

## Common Methods

```php
// Find multiple
->findBy($entity, $criteria, $orderBy, $select, $pagination, $joinConfig)
->findIndexedBy($entity, $indexField, ...) // Key by field value
->findPairsBy($entity, $key, $value, ...)  // Key-value pairs

// Find single
->findOneBy($entity, $criteria, ...)
->findSingleColumnValueBy($entity, $field, $criteria, orderBy: [...], joinConfig: ...) // null for no row and for NULL, findOneBy(select: [$field]) tells them apart

// Column values
->findColumnValuesBy($entity, $field, $criteria, $orderBy, distinct: true)

// Utilities
$queries->countBy($entity, $criteria)
$queries->existsBy($entity, $criteria)  
$queries->deleteBy($entity, $criteria) // no criteria on relations ('author.name'), use ['author' => $queries->subQuery(...)]

// Subqueries
$queries->subQuery($entity, $criteria, select: ['id']) // has to select exactly one column
```

## Results

```php
// As array
->asArray()

// Memory-efficient iteration
->yield()

// For key-value pairs
->findPairsBy(User::class, 'id', 'name')->asArray()
// Returns: [1 => 'John', 2 => 'Jane']

// asArray() of findPairsBy()/findIndexedBy() throws InvalidResultKeyException for a NULL or object key:
// exclude NULL with ['field !=' => null], use scalars for database values, or iterate over yield()
// A wildcard ('*', 'author.**') where a single field is expected throws MultipleFieldSelectionException
```

## Performance Tips

1. Use `arrays` for better performance vs `objects`
2. Use `scalars` for primitive values only
3. Use `yield()` for large datasets
4. Select only needed fields
5. Use INNER joins when relations exist
6. Use subqueries for complex filtering
