<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Exception;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use RuntimeException;

final class NoClassMetadataException extends RuntimeException
{

	public function __construct(
		public readonly string $entity,
	)
	{
		parent::__construct(sprintf('No class metadata found for entity %s', $entity));
	}

	public function toPhpstanError(): IdentifierRuleError
	{
		return RuleErrorBuilder::message(
			sprintf('The entity class `%s` does not exist or is not managed by Doctrine.', $this->entity),
		)
			->identifier('doctrineQueries.invalidEntityClass')
			->build();
	}

}
