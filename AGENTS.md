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

// Operators  
['age >' => 18, 'status !=' => 'banned', 'score <=' => 100]

// LIKE patterns
['name LIKE' => '%john%', 'email NOT LIKE' => '%spam%']

// Null handling
['deleted_at' => null, 'active !=' => null]

// Arrays (IN/NOT IN)
['status' => ['active', 'pending'], 'id !=' => [1, 2, 3]]

// Multiple criteria (AND)
['age >' => 18, 'status' => 'active', 'city' => ['Prague', 'Brno']]
```

## Field Selection

```php
// Specific fields
select: ['id', 'name', 'email']

// With aliases
select: ['id' => 'userId', 'name' => 'fullName']

// Related fields (auto-joins)
select: ['title', 'author.name', 'category.name']

// Wildcards
select: ['*']        // All entity fields (no relations)
select: ['**']       // All fields + relation IDs
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
->findSingleColumnValueBy($entity, $field, $criteria)

// Column values
->findColumnValuesBy($entity, $field, $criteria, $orderBy, distinct: true)

// Utilities
$queries->countBy($entity, $criteria)
$queries->existsBy($entity, $criteria)  
$queries->deleteBy($entity, $criteria)

// Subqueries
$queries->subQuery($entity, $criteria, select: ['id'])
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
```

## Performance Tips

1. Use `arrays` for better performance vs `objects`
2. Use `scalars` for primitive values only
3. Use `yield()` for large datasets
4. Select only needed fields
5. Use INNER joins when relations exist
6. Use subqueries for complex filtering
