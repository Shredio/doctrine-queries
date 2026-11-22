<?php declare(strict_types = 1);

namespace Tests\Unit\Mapping;

use Doctrine\ORM\Tools\SchemaValidator;
use Tests\Context\DoctrineContext;
use Tests\TestCase;

/**
 * The test entities are the fixture of the whole suite, an invalid mapping would make the other tests lie.
 */
final class EntityMappingTest extends TestCase
{

	use DoctrineContext;

	public function testMappingIsValid(): void
	{
		$this->assertSame([], (new SchemaValidator($this->getEntityManager()))->validateMapping());
	}

	protected function setUpDatabase(): bool
	{
		return false;
	}

}
