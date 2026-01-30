<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\ValueObject;

use Cortex\ValueObject\RegisteredClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\ValueObject\RegisteredClass
 */
class RegisteredClassTest extends TestCase
{
    // =======================================================================
    // CONSTRUCTION TESTS
    // =======================================================================

    public function testConstructWithExistingClass(): void
    {
        $registered = new RegisteredClass(\stdClass::class);

        $this->assertEquals(\stdClass::class, $registered->value);
    }

    public function testConstructWithExistingInterface(): void
    {
        $registered = new RegisteredClass(\Iterator::class);

        $this->assertEquals(\Iterator::class, $registered->value);
    }

    public function testConstructWithNonExistentClassThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        new RegisteredClass('NonExistent\\Class\\Name');
    }

    // =======================================================================
    // EXISTS TESTS
    // =======================================================================

    public function testExistsWithClass(): void
    {
        $this->assertTrue(RegisteredClass::exists(\stdClass::class));
    }

    public function testExistsWithInterface(): void
    {
        $this->assertTrue(RegisteredClass::exists(\Iterator::class));
    }

    public function testExistsWithNonExistent(): void
    {
        $this->assertFalse(RegisteredClass::exists('NonExistent\\Class'));
    }

    // =======================================================================
    // isInstanceOf TESTS
    // =======================================================================

    public function testIsInstanceOfWithMatchingClass(): void
    {
        $registered = new RegisteredClass(\ArrayIterator::class);

        $this->assertTrue($registered->isInstanceOf(\Iterator::class));
    }

    public function testIsInstanceOfWithNonMatchingClass(): void
    {
        $registered = new RegisteredClass(\stdClass::class);

        $this->assertFalse($registered->isInstanceOf(\Iterator::class));
    }

    public function testIsInstanceOfWithSameClass(): void
    {
        $registered = new RegisteredClass(\stdClass::class);

        $this->assertTrue($registered->isInstanceOf(\stdClass::class));
    }

    public function testIsInstanceOfWithObject(): void
    {
        $registered = new RegisteredClass(\ArrayIterator::class);
        $iterator = new \ArrayIterator([]);

        // isInstanceOf expects a class string, not an object instance
        $this->assertTrue($registered->isInstanceOf(get_class($iterator)));
    }

    // =======================================================================
    // assertIsInstanceOf TESTS
    // =======================================================================

    public function testAssertIsInstanceOfSuccess(): void
    {
        $registered = new RegisteredClass(\ArrayIterator::class);

        $result = $registered->assertIsInstanceOf(\Iterator::class);

        $this->assertEquals(\Iterator::class, $result);
    }

    public function testAssertIsInstanceOfFailure(): void
    {
        $registered = new RegisteredClass(\stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a');

        $registered->assertIsInstanceOf(\Iterator::class);
    }

    // =======================================================================
    // instanceOf TESTS (reverse check)
    // =======================================================================

    public function testInstanceOfWithMatchingObject(): void
    {
        $registered = new RegisteredClass(\Iterator::class);
        $iterator = new \ArrayIterator([]);

        $this->assertTrue($registered->instanceOf($iterator));
    }

    public function testInstanceOfWithNonMatchingObject(): void
    {
        $registered = new RegisteredClass(\Iterator::class);
        $obj = new \stdClass();

        $this->assertFalse($registered->instanceOf($obj));
    }

    public function testInstanceOfWithMatchingClass(): void
    {
        $registered = new RegisteredClass(\Iterator::class);

        $this->assertTrue($registered->instanceOf(\ArrayIterator::class));
    }

    public function testInstanceOfWithNonMatchingClass(): void
    {
        $registered = new RegisteredClass(\Iterator::class);

        $this->assertFalse($registered->instanceOf(\stdClass::class));
    }

    // =======================================================================
    // assertInstanceOf TESTS (reverse check)
    // =======================================================================

    public function testAssertInstanceOfSuccess(): void
    {
        $registered = new RegisteredClass(\Iterator::class);
        $iterator = new \ArrayIterator([]);

        $result = $registered->assertInstanceOf($iterator);

        $this->assertSame($iterator, $result);
    }

    public function testAssertInstanceOfWithClassSuccess(): void
    {
        $registered = new RegisteredClass(\Iterator::class);

        $result = $registered->assertInstanceOf(\ArrayIterator::class);

        $this->assertEquals(\ArrayIterator::class, $result);
    }

    public function testAssertInstanceOfFailure(): void
    {
        $registered = new RegisteredClass(\Iterator::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected instance of');

        $registered->assertInstanceOf(new \stdClass());
    }

    // =======================================================================
    // VALUE OBJECT BEHAVIOR TESTS
    // =======================================================================

    public function testToString(): void
    {
        $registered = new RegisteredClass(\stdClass::class);

        $this->assertEquals(\stdClass::class, (string) $registered);
    }

    public function testEquals(): void
    {
        $registered1 = new RegisteredClass(\stdClass::class);
        $registered2 = new RegisteredClass(\stdClass::class);
        $registered3 = new RegisteredClass(\ArrayIterator::class);

        $this->assertTrue($registered1->equals($registered2));
        $this->assertFalse($registered1->equals($registered3));
    }

    public function testInvoke(): void
    {
        $registered = new RegisteredClass(\stdClass::class);

        $this->assertEquals(\stdClass::class, $registered());
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testWithAbstractClass(): void
    {
        // Abstract classes should work
        $registered = new RegisteredClass(\IteratorAggregate::class);

        $this->assertEquals(\IteratorAggregate::class, $registered->value);
    }

    public function testWithTrait(): void
    {
        // Traits are not classes or interfaces
        $this->assertFalse(RegisteredClass::exists('SomeTrait'));
    }

    public function testBuiltInClasses(): void
    {
        $registered = new RegisteredClass(\DateTimeImmutable::class);

        $this->assertEquals(\DateTimeImmutable::class, $registered->value);
    }

    public function testExceptionClasses(): void
    {
        $registered = new RegisteredClass(\RuntimeException::class);

        $this->assertTrue($registered->isInstanceOf(\Exception::class));
        $this->assertTrue($registered->isInstanceOf(\Throwable::class));
    }
}
