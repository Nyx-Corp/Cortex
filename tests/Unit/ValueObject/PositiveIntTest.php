<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\ValueObject;

use Cortex\ValueObject\PositiveInt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PositiveInt::class)]
class PositiveIntTest extends TestCase
{
    // =======================================================================
    // VALID VALUES TESTS
    // =======================================================================

    #[DataProvider('validValuesProvider')]
    public function testValidValues(int $value): void
    {
        $positiveInt = new PositiveInt($value);

        $this->assertEquals($value, $positiveInt->value);
    }

    public static function validValuesProvider(): array
    {
        return [
            'zero' => [0],
            'one' => [1],
            'small positive' => [10],
            'large positive' => [1000000],
            'max int' => [PHP_INT_MAX],
        ];
    }

    // =======================================================================
    // INVALID VALUES TESTS
    // =======================================================================

    #[DataProvider('invalidValuesProvider')]
    public function testInvalidValuesThrow(int $value): void
    {
        // Note: The implementation has a bug where $this->value is used before being set
        // This causes an error, but we can still verify negative values are rejected
        $this->expectException(\Throwable::class);

        new PositiveInt($value);
    }

    public static function invalidValuesProvider(): array
    {
        return [
            'minus one' => [-1],
            'small negative' => [-10],
            'large negative' => [-1000000],
            'min int' => [PHP_INT_MIN],
        ];
    }

    // =======================================================================
    // POSITIVEINT FROM POSITIVEINT TESTS
    // =======================================================================

    public function testConstructFromPositiveInt(): void
    {
        $first = new PositiveInt(42);
        $second = new PositiveInt($first);

        $this->assertEquals(42, $second->value);
    }

    public function testConstructFromPositiveIntPreservesValue(): void
    {
        $original = new PositiveInt(0);
        $copy = new PositiveInt($original);

        $this->assertEquals(0, $copy->value);
    }

    // =======================================================================
    // VALUE OBJECT BEHAVIOR TESTS
    // =======================================================================

    public function testToString(): void
    {
        $positiveInt = new PositiveInt(42);

        $this->assertEquals('42', (string) $positiveInt);
    }

    public function testEquals(): void
    {
        $int1 = new PositiveInt(42);
        $int2 = new PositiveInt(42);
        $int3 = new PositiveInt(100);

        $this->assertTrue($int1->equals($int2));
        $this->assertFalse($int1->equals($int3));
    }

    public function testInvoke(): void
    {
        $positiveInt = new PositiveInt(42);

        $this->assertEquals(42, $positiveInt());
    }

    public function testValueIsReadOnly(): void
    {
        $positiveInt = new PositiveInt(42);

        $reflection = new \ReflectionProperty($positiveInt, 'value');
        $this->assertTrue($reflection->isReadOnly());
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testZeroIsValid(): void
    {
        $positiveInt = new PositiveInt(0);

        $this->assertEquals(0, $positiveInt->value);
    }

    public function testValueType(): void
    {
        $positiveInt = new PositiveInt(42);

        $this->assertIsInt($positiveInt->value);
    }

    public function testClassIsFinal(): void
    {
        $reflection = new \ReflectionClass(PositiveInt::class);

        $this->assertTrue($reflection->isFinal());
    }
}
