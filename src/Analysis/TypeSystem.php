<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

/**
 * The types of a static analyser as the shared analysis of the queries reads and builds them. The PHPStan extension
 * implements it with PHPStan types, an extension of another analyser (e.g. Mago) implements it with its own types.
 *
 * The methods starting with "is" tell whether the type certainly is of the kind, a type that only may be of it is not.
 *
 * @template TType of object
 */
interface TypeSystem
{

	/**
	 * The literal strings of the type (e.g. 'a' and 'b' of 'a'|'b'), none when a member is not a literal string.
	 *
	 * @param TType $type
	 * @return list<string>
	 */
	public function getConstantStrings(object $type): array;

	/**
	 * @param TType $type
	 * @return list<int|float|string|bool|null>
	 */
	public function getConstantScalarValues(object $type): array;

	/**
	 * The literal members of the type (e.g. 1, 'a', true, null), none when a member is not a literal.
	 *
	 * @param TType $type
	 * @return list<TType>
	 */
	public function getConstantScalarTypes(object $type): array;

	/**
	 * The members of a union (e.g. int and null of ?int), the type itself when it is not a union.
	 *
	 * @param TType $type
	 * @return list<TType>
	 */
	public function getUnionMembers(object $type): array;

	/**
	 * Whether the type is a single case of an enum, e.g. Suit::Hearts.
	 *
	 * @param TType $type
	 */
	public function isEnumCase(object $type): bool;

	/**
	 * The type of the value of a case of a backed enum (e.g. 'hearts'), null when the type is not such a case.
	 *
	 * @param TType $type
	 * @return TType|null
	 */
	public function getEnumCaseValueType(object $type): ?object;

	/**
	 * @param TType $type
	 */
	public function isConstantArray(object $type): bool;

	/**
	 * The arrays of the type whose keys are all known, e.g. array{id: int, name?: string}.
	 *
	 * @param TType $type
	 * @return list<ConstantArray<TType>>
	 */
	public function getConstantArrays(object $type): array;

	/**
	 * @param TType $type
	 * @return TType
	 */
	public function getIterableValueType(object $type): object;

	/**
	 * The types of the alternatives of a group created by Criteria::anyOf(), null when the type is not such a group.
	 * Alternatives whose number is not known, e.g. unpacked from a list, are returned as one type.
	 *
	 * @param TType $type
	 * @return list<TType>|null
	 */
	public function getAnyOfAlternatives(object $type): ?array;

	/**
	 * The kind of the scalar type, null when the type is not certainly of a single kind (e.g. int|string, mixed, ?int).
	 *
	 * @param TType $type
	 */
	public function getScalarKind(object $type): ?ScalarKind;

	/**
	 * @param TType $type
	 */
	public function isScalar(object $type): bool;

	/**
	 * @param TType $type
	 */
	public function isNull(object $type): bool;

	/**
	 * Whether a value of the type can be null, e.g. ?int or mixed.
	 *
	 * @param TType $type
	 */
	public function canBeNull(object $type): bool;

	/**
	 * Whether null is a member of the type, e.g. ?int, but not mixed.
	 *
	 * @param TType $type
	 */
	public function containsNull(object $type): bool;

	/**
	 * @param TType $type
	 */
	public function isArray(object $type): bool;

	/**
	 * Whether a value of the type can be iterated and an iterated value can be null, e.g. list<?int>, iterable<mixed>
	 * or mixed. Only the members of the type that can be iterated are considered, int|list<int> iterates no null.
	 * An object is iterated only when it certainly is Traversable, not when a subclass of its class could be.
	 *
	 * @param TType $type
	 */
	public function canIterateNull(object $type): bool;

	/**
	 * @param TType $type
	 */
	public function isNever(object $type): bool;

	/**
	 * @param TType $type
	 */
	public function isMixed(object $type): bool;

	/**
	 * Whether every value of the type is accepted as an array key, an integer or a string.
	 *
	 * @param TType $type
	 */
	public function isArrayKey(object $type): bool;

	/**
	 * @param TType $type
	 */
	public function describe(object $type): string;

	/**
	 * @return TType
	 */
	public function mixed(): object;

	/**
	 * @return TType
	 */
	public function never(): object;

	/**
	 * @return TType
	 */
	public function null(): object;

	/**
	 * The type of any array key, int|string.
	 *
	 * @return TType
	 */
	public function arrayKey(): object;

	/**
	 * @return TType
	 */
	public function float(): object;

	/**
	 * A string holding a number, e.g. '12.50'.
	 *
	 * @return TType
	 */
	public function numericString(): object;

	/**
	 * The integers from $min to $max, null for no limit, e.g. intRange(1, null) is positive-int.
	 *
	 * @return TType
	 */
	public function intRange(?int $min, ?int $max): object;

	/**
	 * @param TType $type
	 * @return TType
	 */
	public function addNull(object $type): object;

	/**
	 * @param TType $type
	 * @return TType
	 */
	public function removeNull(object $type): object;

	/**
	 * @param TType $type
	 * @param TType ...$types
	 * @return TType
	 */
	public function union(object $type, object ...$types): object;

	/**
	 * @param TType $type
	 * @param TType $otherType
	 * @return TType
	 */
	public function intersect(object $type, object $otherType): object;

	/**
	 * @param TType $type
	 * @param TType $typeToRemove
	 * @return TType
	 */
	public function remove(object $type, object $typeToRemove): object;

	/**
	 * The type the values have as array keys, e.g. '7' becomes 7. Objects are kept as they are.
	 *
	 * @param TType $type
	 * @return TType
	 */
	public function toArrayKey(object $type): object;

	/**
	 * An array with the given keys, all of them required.
	 *
	 * @param array<array-key, TType> $valueTypes
	 * @return TType
	 */
	public function constantArray(array $valueTypes): object;

	/**
	 * @param class-string $className
	 * @param list<TType> $templateTypes
	 * @return TType
	 */
	public function genericObject(string $className, array $templateTypes): object;

}
