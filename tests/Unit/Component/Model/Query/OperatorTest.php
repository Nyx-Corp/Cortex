<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Model\Query;

use Cortex\Component\Model\Query\Operator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Component\Model\Query\Operator
 */
class OperatorTest extends TestCase
{
    // =======================================================================
    // ENUM VALUES TESTS
    // =======================================================================

    public function testEqualValue(): void
    {
        $this->assertEquals('=', Operator::Equal->value);
    }

    public function testNotEqualValue(): void
    {
        $this->assertEquals('!=', Operator::NotEqual->value);
    }

    public function testGreaterThanValue(): void
    {
        $this->assertEquals('>', Operator::GreaterThan->value);
    }

    public function testGreaterThanOrEqualValue(): void
    {
        $this->assertEquals('>=', Operator::GreaterThanOrEqual->value);
    }

    public function testLessThanValue(): void
    {
        $this->assertEquals('<', Operator::LessThan->value);
    }

    public function testLessThanOrEqualValue(): void
    {
        $this->assertEquals('<=', Operator::LessThanOrEqual->value);
    }

    public function testLikeValue(): void
    {
        $this->assertEquals('~', Operator::Like->value);
    }

    public function testNotLikeValue(): void
    {
        $this->assertEquals('!~', Operator::NotLike->value);
    }

    // =======================================================================
    // toSql() TESTS
    // =======================================================================

    public function testToSqlEqual(): void
    {
        $this->assertEquals('=', Operator::Equal->toSql());
    }

    public function testToSqlNotEqual(): void
    {
        $this->assertEquals('!=', Operator::NotEqual->toSql());
    }

    public function testToSqlGreaterThan(): void
    {
        $this->assertEquals('>', Operator::GreaterThan->toSql());
    }

    public function testToSqlGreaterThanOrEqual(): void
    {
        $this->assertEquals('>=', Operator::GreaterThanOrEqual->toSql());
    }

    public function testToSqlLessThan(): void
    {
        $this->assertEquals('<', Operator::LessThan->toSql());
    }

    public function testToSqlLessThanOrEqual(): void
    {
        $this->assertEquals('<=', Operator::LessThanOrEqual->toSql());
    }

    public function testToSqlLike(): void
    {
        $this->assertEquals('LIKE', Operator::Like->toSql());
    }

    public function testToSqlNotLike(): void
    {
        $this->assertEquals('NOT LIKE', Operator::NotLike->toSql());
    }

    // =======================================================================
    // pattern() TESTS
    // =======================================================================

    public function testPatternReturnsRegex(): void
    {
        $pattern = Operator::pattern();

        // Should match all operators
        $this->assertMatchesRegularExpression("/^($pattern)/", '=value');
        $this->assertMatchesRegularExpression("/^($pattern)/", '!=value');
        $this->assertMatchesRegularExpression("/^($pattern)/", '>value');
        $this->assertMatchesRegularExpression("/^($pattern)/", '>=value');
        $this->assertMatchesRegularExpression("/^($pattern)/", '<value');
        $this->assertMatchesRegularExpression("/^($pattern)/", '<=value');
        $this->assertMatchesRegularExpression("/^($pattern)/", '~value');
        $this->assertMatchesRegularExpression("/^($pattern)/", '!~value');
    }

    public function testPatternDoesNotMatchPlainStrings(): void
    {
        $pattern = Operator::pattern();

        // Plain strings without operators don't match
        $this->assertDoesNotMatchRegularExpression("/^($pattern)/", 'value');
        $this->assertDoesNotMatchRegularExpression("/^($pattern)/", 'test123');
    }

    // =======================================================================
    // hasOperator() TESTS
    // =======================================================================

    /**
     * @dataProvider validOperatorValuesProvider
     */
    public function testHasOperatorWithValidValues(string $value): void
    {
        $this->assertTrue(Operator::hasOperator($value));
    }

    public function validOperatorValuesProvider(): array
    {
        return [
            'equal' => ['=test'],
            'not equal' => ['!=test'],
            'greater than' => ['>100'],
            'greater than or equal' => ['>=100'],
            'less than' => ['<50'],
            'less than or equal' => ['<=50'],
            'like' => ['~pattern%'],
            'not like' => ['!~pattern%'],
            'equal with spaces' => ['= test'],
            'greater than number' => ['>0'],
        ];
    }

    /**
     * @dataProvider invalidOperatorValuesProvider
     */
    public function testHasOperatorWithInvalidValues(mixed $value): void
    {
        $this->assertFalse(Operator::hasOperator($value));
    }

    public function invalidOperatorValuesProvider(): array
    {
        return [
            'plain string' => ['test'],
            'number in string' => ['100'],
            'empty string' => [''],
            'null' => [null],
            'integer' => [100],
            'float' => [3.14],
            'boolean true' => [true],
            'boolean false' => [false],
            'array' => [['=test']],
            'object' => [new \stdClass()],
            'operator at end' => ['test='],
            'operator in middle' => ['test=value'],
        ];
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testHasOperatorWithJustOperator(): void
    {
        $this->assertTrue(Operator::hasOperator('='));
        $this->assertTrue(Operator::hasOperator('!='));
        $this->assertTrue(Operator::hasOperator('>'));
        $this->assertTrue(Operator::hasOperator('>='));
        $this->assertTrue(Operator::hasOperator('<'));
        $this->assertTrue(Operator::hasOperator('<='));
        $this->assertTrue(Operator::hasOperator('~'));
        $this->assertTrue(Operator::hasOperator('!~'));
    }

    public function testHasOperatorWithSpecialCharacters(): void
    {
        $this->assertTrue(Operator::hasOperator('>=特殊文字'));
        $this->assertTrue(Operator::hasOperator('~%emoji🎉%'));
    }

    public function testHasOperatorWithNewlines(): void
    {
        $this->assertTrue(Operator::hasOperator("=test\nvalue"));
    }

    public function testAllCasesHaveValues(): void
    {
        foreach (Operator::cases() as $operator) {
            $this->assertNotEmpty($operator->value);
            $this->assertNotEmpty($operator->toSql());
        }
    }
}
