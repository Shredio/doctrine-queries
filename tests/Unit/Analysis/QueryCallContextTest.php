<?php declare(strict_types = 1);

namespace Tests\Unit\Analysis;

use Shredio\DoctrineQueries\Analysis\QueryCallContext;
use Tests\TestCase;

final class QueryCallContextTest extends TestCase
{

	public function testNormalizeJoinConfig(): void
	{
		$this->assertSame([
			'user' => 'inner',
			'user.role' => 'inner',
		], QueryCallContext::normalizeJoinConfig([
			'user' => 'left',
			'user.role' => 'inner',
		]));

		$this->assertSame([
			'user' => 'inner',
			'user.role' => 'left',
			'other' => 'left',
			'another' => 'inner',
		], QueryCallContext::normalizeJoinConfig([
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
		], QueryCallContext::normalizeJoinConfig([
			'user' => 'left',
			'user.role' => 'left',
			'user.role.sub_role' => 'inner',
			'other' => 'left',
			'another' => 'inner',
		]));
	}

}
