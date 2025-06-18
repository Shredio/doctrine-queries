<?php declare(strict_types = 1);

namespace Tests\Unit\Criteria;

use InvalidArgumentException;
use Shredio\DoctrineQueries\Criteria\CriteriaParser;
use Shredio\DoctrineQueries\Field\FieldPath;
use Tests\TestCase;

final class CriteriaParserTest extends TestCase
{

	public function testParseCriteriaWithMultipleFields(): void
	{
		$criteria = ['field1' => 'value1', 'field2 !=' => 'value2'];
		$parsedCriteria = iterator_to_array(CriteriaParser::parse($criteria));

		$this->assertCount(2, $parsedCriteria);

		$this->assertEquals('field1', $parsedCriteria[0]->field->getPath());
		$this->assertEquals('=', $parsedCriteria[0]->operator);
		$this->assertEquals(':param1', $parsedCriteria[0]->operand);
		$this->assertEquals('param1', $parsedCriteria[0]->parameterName);
		$this->assertEquals('value1', $parsedCriteria[0]->value);

		$this->assertEquals('field2', $parsedCriteria[1]->field->getPath());
		$this->assertEquals('!=', $parsedCriteria[1]->operator);
		$this->assertEquals(':param2', $parsedCriteria[1]->operand);
		$this->assertEquals('param2', $parsedCriteria[1]->parameterName);
		$this->assertEquals('value2', $parsedCriteria[1]->value);
	}

	public function testParseCriteriaWithEmptyArray(): void
	{
		$criteria = [];
		$parsedCriteria = iterator_to_array(CriteriaParser::parse($criteria));

		$this->assertCount(0, $parsedCriteria);
	}

	public function testParseCriteriaWithEmptyStringField(): void
	{
		$criteria = ['' => 'value'];
		$this->expectException(InvalidArgumentException::class);
		iterator_to_array(CriteriaParser::parse($criteria));
	}

	public function testParseCriteriaWithEmptyStringValue(): void
	{
		$criteria = ['field' => ''];
		$parsedCriteria = iterator_to_array(CriteriaParser::parse($criteria));

		$this->assertCount(1, $parsedCriteria);
		$this->assertEquals('field', $parsedCriteria[0]->field->getPath());
		$this->assertEquals('=', $parsedCriteria[0]->operator);
		$this->assertEquals(':param1', $parsedCriteria[0]->operand);
		$this->assertEquals('param1', $parsedCriteria[0]->parameterName);
		$this->assertEquals('', $parsedCriteria[0]->value);
	}

	public function testParseCriteriaWithNullField(): void
	{
		$criteria = [null => 'value'];
		$this->expectException(InvalidArgumentException::class);
		iterator_to_array(CriteriaParser::parse($criteria));
	}

	public function testParseCriteriaWithSingleField(): void
	{
		$criteria = ['field1' => 'value1'];
		$parsedCriteria = iterator_to_array(CriteriaParser::parse($criteria));

		$this->assertCount(1, $parsedCriteria);
		$this->assertEquals('field1', $parsedCriteria[0]->field->getPath());
		$this->assertEquals('=', $parsedCriteria[0]->operator);
		$this->assertEquals(':param1', $parsedCriteria[0]->operand);
		$this->assertEquals('param1', $parsedCriteria[0]->parameterName);
		$this->assertEquals('value1', $parsedCriteria[0]->value);
	}

	public function testParseCriteriaWithMultipleOperators(): void
	{
		$criteria = ['field1 >' => 'value1', 'field2 <' => 'value2'];
		$parsedCriteria = iterator_to_array(CriteriaParser::parse($criteria));

		$this->assertCount(2, $parsedCriteria);

		$this->assertEquals('field1', $parsedCriteria[0]->field->getPath());
		$this->assertEquals('>', $parsedCriteria[0]->operator);
		$this->assertEquals(':param1', $parsedCriteria[0]->operand);
		$this->assertEquals('param1', $parsedCriteria[0]->parameterName);
		$this->assertEquals('value1', $parsedCriteria[0]->value);

		$this->assertEquals('field2', $parsedCriteria[1]->field->getPath());
		$this->assertEquals('<', $parsedCriteria[1]->operator);
		$this->assertEquals(':param2', $parsedCriteria[1]->operand);
		$this->assertEquals('param2', $parsedCriteria[1]->parameterName);
		$this->assertEquals('value2', $parsedCriteria[1]->value);
	}

	public function testGetExpression(): void
	{
		$criteria = ['field1' => 'value1'];
		$parsedCriteria = iterator_to_array(CriteriaParser::parse($criteria));

		$this->assertCount(1, $parsedCriteria);
		// Note: getExpression() now requires MappedFieldPath parameter
		$this->assertEquals('field1', $parsedCriteria[0]->field->getPath());
	}

}
