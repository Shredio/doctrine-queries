<?php declare(strict_types = 1);

namespace Tests\Unit\Field;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Shredio\DoctrineQueries\Field\FieldPath;

final class FieldPathTest extends TestCase
{

	public function testCreateFromString(): void
	{
		$fieldPath = FieldPath::createFromString('account.role.name');
		
		$this->assertSame('account.role.name', $fieldPath->getPath());
	}

	public function testCreateFromStringWithEmptyPathThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Path cannot be empty');
		
		FieldPath::createFromString('');
	}

	public function testGetRootWithSingleSegment(): void
	{
		$fieldPath = FieldPath::createFromString('account');
		
		$this->assertSame('account', $fieldPath->getRoot());
	}

	public function testGetRootWithMultipleSegments(): void
	{
		$fieldPath = FieldPath::createFromString('account.role.name');
		
		$this->assertSame('account', $fieldPath->getRoot());
	}

	public function testGetParentWithSingleSegment(): void
	{
		$fieldPath = FieldPath::createFromString('account');
		
		$this->assertNull($fieldPath->getParent());
	}

	public function testGetParentWithTwoSegments(): void
	{
		$fieldPath = FieldPath::createFromString('account.id');
		
		$this->assertSame('account', $fieldPath->getParent());
	}

	public function testGetParentWithThreeSegments(): void
	{
		$fieldPath = FieldPath::createFromString('account.role.name');
		
		$this->assertSame('account.role', $fieldPath->getParent());
	}

	public function testNextWithSingleSegment(): void
	{
		$fieldPath = FieldPath::createFromString('account');
		
		$this->assertNull($fieldPath->next());
	}

	public function testNextWithTwoSegments(): void
	{
		$fieldPath = FieldPath::createFromString('account.id');
		$next = $fieldPath->next();
		
		$this->assertInstanceOf(FieldPath::class, $next);
		$this->assertSame('id', $next->getPath());
	}

	public function testNextWithThreeSegments(): void
	{
		$fieldPath = FieldPath::createFromString('account.role.name');
		$next = $fieldPath->next();
		
		$this->assertInstanceOf(FieldPath::class, $next);
		$this->assertSame('role.name', $next->getPath());
	}

}
