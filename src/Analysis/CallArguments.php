<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Analysis;

/**
 * The arguments of an analysed call.
 *
 * @template TType of object
 */
final readonly class CallArguments
{

	/**
	 * @param list<CallArgument<TType>> $arguments In the order of the call, the named arguments follow the positional ones
	 */
	public function __construct(
		public array $arguments,
	)
	{
	}

	/**
	 * The argument of a parameter, passed by its name or at its position.
	 *
	 * @return CallArgument<TType>|null
	 */
	public function get(int $position, string $name): ?CallArgument
	{
		foreach ($this->arguments as $argument) {
			if ($argument->name === $name) {
				return $argument;
			}
		}

		$argument = $this->arguments[$position] ?? null;

		return $argument?->name === null ? $argument : null;
	}

	/**
	 * @return TType|null
	 */
	public function getType(int $position, string $name): ?object
	{
		return $this->get($position, $name)?->type;
	}

}
