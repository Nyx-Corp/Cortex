<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Model\Query;

use Cortex\Component\Model\Query\Operator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operator::class)]
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

    public function testIsNullValue(): void
    {
        $this->assertEquals('IS_NULL', Operator::IsNull->value);
    }

    public function testIsNotNullValue(): void
    {
        $this->assertEquals('IS_NOT_NULL', Operator::IsNotNull->value);
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

    public function testToSqlIsNull(): void
    {
        $this->assertEquals('IS NULL', Operator::IsNull->toSql());
    }

    public function testToSqlIsNotNull(): void
    {
        $this->assertEquals('IS NOT NULL', Operator::IsNotNull->toSql());
    }

    // =======================================================================
    // isUnary() TESTS
    // =======================================================================

    public function testIsUnaryForNullOperators(): void
    {
        $this->assertTrue(Operator::IsNull->isUnary());
        $this->assertTrue(Operator::IsNotNull->isUnary());
    }

    public function testIsUnaryForRegularOperators(): void
    {
        $this->assertFalse(Operator::Equal->isUnary());
        $this->assertFalse(Operator::NotEqual->isUnary());
        $this->assertFalse(Operator::GreaterThan->isUnary());
        $this->assertFalse(Operator::Like->isUnary());
        $this->assertFalse(Operator::NotLike->isUnary());
    }

    // =======================================================================
    // pattern() TESTS
    // =======================================================================

    public function testPatternReturnsRegex(): void
    {
        $pattern = Operator::pattern();

        // Should match all prefix operators
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

        $this->assertDoesNotMatchRegularExpression("/^($pattern)/", 'value');
        $this->assertDoesNotMatchRegularExpression("/^($pattern)/", 'test123');
    }

    public function testPatternExcludesUnaryOperators(): void
    {
        $pattern = Operator::pattern();

        // Unary operators are not prefix operators — they should not be in the pattern
        $this->assertDoesNotMatchRegularExpression("/^($pattern)/", 'IS_NULL');
        $this->assertDoesNotMatchRegularExpression("/^($pattern)/", 'IS_NOT_NULL');
    }

    // =======================================================================
    // hasOperator() TESTS
    // =======================================================================

    #[DataProvider('validOperatorValuesProvider')]
    public function testHasOperatorWithValidValues(string $value): void
    {
        $this->assertTrue(Operator::hasOperator($value));
    }

    public static function validOperatorValuesProvider(): array
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

    #[DataProvider('invalidOperatorValuesProvider')]
    public function testHasOperatorWithInvalidValues(mixed $value): void
    {
        $this->assertFalse(Operator::hasOperator($value));
    }

    public static function invalidOperatorValuesProvider(): array
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
            'IS_NULL string' => ['IS_NULL'],
            'IS_NOT_NULL string' => ['IS_NOT_NULL'],
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
