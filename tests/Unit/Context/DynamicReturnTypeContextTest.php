<?php declare(strict_types = 1);

namespace Tests\Unit\Context;

use Shredio\DoctrineQueries\PhpStan\TypeExtension\DynamicReturnTypeContext;
use Tests\TestCase;

final class DynamicReturnTypeContextTest extends TestCase
{

	public function testFixJoinConfig(): void
	{
		$this->assertSame([
			'user' => 'inner',
			'user.role' => 'inner',
		], DynamicReturnTypeContext::fixJoinConfig([
			'user' => 'left',
			'user.role' => 'inner',
		]));

		$this->assertSame([
			'user' => 'inner',
			'user.role' => 'left',
			'other' => 'left',
			'another' => 'inner',
		], DynamicReturnTypeContext::fixJoinConfig([
			'user' => 'inner',
			'user.role' => 'left',
			'other' => 'left',
			'another' => 'inner',
		]));

		$this->assertSame([
			'user' => 'inner',
			'user.role' => 'inner',
			'user.role.sub_role' => 'inner',
			'other' => 'left',
			'another' => 'inner',
		], DynamicReturnTypeContext::fixJoinConfig([
			'user' => 'left',
			'user.role' => 'left',
			'user.role.sub_role' => 'inner',
			'other' => 'left',
			'another' => 'inner',
		]));
	}

}
