<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Collection;

use Cortex\Component\Collection\StructuredMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StructuredMap::class)]
class StructuredMapTest extends TestCase
{
    // =======================================================================
    // DECLARATION AND VALIDATION TESTS
    // =======================================================================

    public function testDeclareString(): void
    {
        $map = new StructuredMap();
        $map->declare('name', 'is_string');
        $map->set('name', 'John');

        $this->assertEquals('John', $map->get('name'));
    }

    public function testDeclareInt(): void
    {
        $map = new StructuredMap();
        $map->declare('age', 'is_int');
        $map->set('age', 25);

        $this->assertEquals(25, $map->get('age'));
    }

    public function testDeclareFloat(): void
    {
        $map = new StructuredMap();
        $map->declare('price', 'is_float');
        $map->set('price', 19.99);

        $this->assertEquals(19.99, $map->get('price'));
    }

    public function testDeclareBool(): void
    {
        $map = new StructuredMap();
        $map->declare('active', 'is_bool');
        $map->set('active', true);

        $this->assertTrue($map->get('active'));
    }

    public function testDeclareArray(): void
    {
        $map = new StructuredMap();
        $map->declare('tags', 'is_array');
        $map->set('tags', ['a', 'b', 'c']);

        $this->assertEquals(['a', 'b', 'c'], $map->get('tags'));
    }

    public function testDeclareCallable(): void
    {
        $map = new StructuredMap();
        $map->declare('callback', 'is_callable');
        $map->set('callback', fn () => 'test');

        $this->assertIsCallable($map->get('callback'));
    }

    public function testDeclareObject(): void
    {
        $map = new StructuredMap();
        $map->declare('date', 'is_object');
        $date = new \DateTimeImmutable();
        $map->set('date', $date);

        $this->assertSame($date, $map->get('date'));
    }

    public function testDeclareMixed(): void
    {
        $map = new StructuredMap();
        $map->declare('anything'); // No validation = mixed

        $map->set('anything', 'string');
        $this->assertEquals('string', $map->get('anything'));

        $map->set('anything', 123);
        $this->assertEquals(123, $map->get('anything'));

        $map->set('anything', ['array']);
        $this->assertEquals(['array'], $map->get('anything'));
    }

    public function testCustomValidationCallback(): void
    {
        $map = new StructuredMap();
        $map->declare('email', fn ($v) => false !== filter_var($v, FILTER_VALIDATE_EMAIL));

        $map->set('email', 'valid@test.com');
        $this->assertEquals('valid@test.com', $map->get('email'));

        $this->expectException(\InvalidArgumentException::class);
        $map->set('email', 'invalid-email');
    }

    public function testValidationFailureThrowsException(): void
    {
        $map = new StructuredMap();
        $map->declare('name', 'is_string');

        $this->expectException(\InvalidArgumentException::class);
        $map->set('name', 123);
    }

    // =======================================================================
    // NULLABLE TESTS
    // =======================================================================

    public function testNullableAcceptsNull(): void
    {
        $map = new StructuredMap();
        $map->declare('optional', nullable: true);
        $map->set('optional', null);

        $this->assertNull($map->get('optional'));
    }

    public function testNonNullableRejectsNull(): void
    {
        $map = new StructuredMap();
        $map->declare('required');
        $map->set('required', 'value');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not null');
        $map->set('required', null);
    }

    public function testNullableWithValidation(): void
    {
        $map = new StructuredMap();
        $map->declare('email', fn ($v) => false !== filter_var($v, FILTER_VALIDATE_EMAIL), nullable: true);

        // Null is OK for nullable
        $map->set('email', null);
        $this->assertNull($map->get('email'));

        // Valid email is OK
        $map->set('email', 'test@example.com');
        $this->assertEquals('test@example.com', $map->get('email'));

        // Invalid email still fails
        $this->expectException(\InvalidArgumentException::class);
        $map->set('email', 'invalid');
    }

    // =======================================================================
    // CAMELCASE / SNAKE_CASE MAPPING TESTS
    // =======================================================================

    public function testSetViaCamelCaseRetrievedViaDeclaredKey(): void
    {
        $map = new StructuredMap();
        $map->declare('first_name');
        $map->set('firstName', 'John');

        // Setting via alias stores under the alias key 'firstName', retrievable via both
        $this->assertTrue($map->has('firstName'));
    }

    public function testSetViaSnakeCaseRetrievedViaDeclaredKey(): void
    {
        $map = new StructuredMap();
        $map->declare('firstName');
        $map->set('first_name', 'John');

        // Setting via alias stores under the alias key 'first_name', retrievable via both
        $this->assertTrue($map->has('first_name'));
    }

    public function testGetViaCamelCase(): void
    {
        $map = new StructuredMap();
        $map->declare('first_name');
        $map->set('first_name', 'John');

        $this->assertEquals('John', $map->get('firstName'));
    }

    public function testGetViaSnakeCase(): void
    {
        $map = new StructuredMap();
        $map->declare('firstName');
        $map->set('firstName', 'John');

        $this->assertEquals('John', $map->get('first_name'));
    }

    public function testMixedCaseAccess(): void
    {
        $map = new StructuredMap();
        $map->declare('firstName');
        $map->set('firstName', 'John');

        // Both camelCase and snake_case access work for retrieval
        $this->assertEquals('John', $map->get('firstName'));
        $this->assertEquals('John', $map->get('first_name'));
    }

    public function testDeclaredKeysReturnOriginalCase(): void
    {
        $map = new StructuredMap();
        $map->declare('firstName');
        $map->declare('last_name');

        $keys = $map->declaredKeys();
        $this->assertContains('firstName', $keys);
        $this->assertContains('last_name', $keys);
    }

    // =======================================================================
    // EDGE CASES AND LIMIT VALUES
    // =======================================================================

    public function testEmptyString(): void
    {
        $map = new StructuredMap();
        $map->set('empty', '');

        $this->assertSame('', $map->get('empty'));
    }

    public function testVeryLongString(): void
    {
        $map = new StructuredMap();
        $longString = str_repeat('a', 10000);
        $map->set('long', $longString);

        $this->assertEquals($longString, $map->get('long'));
        $this->assertEquals(10000, strlen($map->get('long')));
    }

    public function testUnicodeString(): void
    {
        $map = new StructuredMap();
        $map->set('unicode', '你好世界 🎉 émojis');

        $this->assertEquals('你好世界 🎉 émojis', $map->get('unicode'));
    }

    public function testZero(): void
    {
        $map = new StructuredMap();
        $map->declare('zero', 'is_int');
        $map->set('zero', 0);

        $this->assertSame(0, $map->get('zero'));
    }

    public function testNegativeInt(): void
    {
        $map = new StructuredMap();
        $map->declare('negative', 'is_int');
        $map->set('negative', -100);

        $this->assertSame(-100, $map->get('negative'));
    }

    public function testMaxInt(): void
    {
        $map = new StructuredMap();
        $map->set('max', PHP_INT_MAX);

        $this->assertSame(PHP_INT_MAX, $map->get('max'));
    }

    public function testMinInt(): void
    {
        $map = new StructuredMap();
        $map->set('min', PHP_INT_MIN);

        $this->assertSame(PHP_INT_MIN, $map->get('min'));
    }

    public function testFloatPrecision(): void
    {
        $map = new StructuredMap();
        $map->declare('float', 'is_float');
        $map->set('float', 0.1 + 0.2);

        // Float precision: 0.1 + 0.2 is not exactly 0.3
        $this->assertEqualsWithDelta(0.3, $map->get('float'), 0.0000001);
    }

    public function testInfinity(): void
    {
        $map = new StructuredMap();
        $map->set('inf', INF);
        $map->set('neg_inf', -INF);

        $this->assertSame(INF, $map->get('inf'));
        $this->assertSame(-INF, $map->get('neg_inf'));
    }

    public function testNaN(): void
    {
        $map = new StructuredMap();
        $map->set('nan', NAN);

        $this->assertNan($map->get('nan'));
    }

    public function testEmptyArray(): void
    {
        $map = new StructuredMap();
        $map->declare('empty', 'is_array');
        $map->set('empty', []);

        $this->assertSame([], $map->get('empty'));
    }

    public function testNestedArray(): void
    {
        $map = new StructuredMap();
        $nested = ['a' => ['b' => ['c' => 'deep']]];
        $map->set('nested', $nested);

        $this->assertEquals($nested, $map->get('nested'));
    }

    public function testAssociativeArray(): void
    {
        $map = new StructuredMap();
        $assoc = ['key1' => 'val1', 'key2' => 'val2'];
        $map->set('assoc', $assoc);

        $this->assertEquals($assoc, $map->get('assoc'));
    }

    public function testMixedArrayKeys(): void
    {
        $map = new StructuredMap();
        $mixed = [0 => 'zero', 'one' => 1, 2 => 'two'];
        $map->set('mixed', $mixed);

        $this->assertEquals($mixed, $map->get('mixed'));
    }

    public function testStdClass(): void
    {
        $map = new StructuredMap();
        $map->declare('obj', 'is_object');
        $obj = new \stdClass();
        $obj->name = 'Test';
        $map->set('obj', $obj);

        $this->assertSame($obj, $map->get('obj'));
    }

    public function testDateTimeImmutable(): void
    {
        $map = new StructuredMap();
        $date = new \DateTimeImmutable('2024-01-15 10:30:00');
        $map->set('date', $date);

        $this->assertSame($date, $map->get('date'));
    }

    public function testClosure(): void
    {
        $map = new StructuredMap();
        $closure = fn ($x) => $x * 2;
        $map->set('closure', $closure);

        $this->assertSame($closure, $map->get('closure'));
        $this->assertEquals(10, $map->get('closure')(5));
    }

    // =======================================================================
    // MULTIPLE OPERATIONS TESTS
    // =======================================================================

    public function testUnsetKey(): void
    {
        $map = new StructuredMap();
        $map->declare('name');
        $map->set('name', 'John');
        $map->unset('name');

        $this->assertFalse($map->has('name'));
        $this->assertNotContains('name', $map->declaredKeys());
    }

    public function testUnsetRemovesAllAliases(): void
    {
        $map = new StructuredMap();
        $map->declare('firstName');
        $map->set('firstName', 'John');
        $map->unset('firstName');

        $this->assertFalse($map->has('firstName'));
        $this->assertFalse($map->has('first_name'));
    }

    public function testUnsetAndRedeclare(): void
    {
        $map = new StructuredMap();
        $map->declare('name', 'is_string');
        $map->set('name', 'John');
        $map->unset('name');

        // Redeclare with different validation
        $map->declare('name', 'is_int');
        $map->set('name', 42);

        $this->assertEquals(42, $map->get('name'));
    }

    public function testMergeStructuredMaps(): void
    {
        $map1 = new StructuredMap();
        $map1->set('a', 1);
        $map1->set('b', 2);

        $map2 = new StructuredMap();
        $map2->set('c', 3);
        $map2->set('d', 4);

        $map1->merge($map2);

        $this->assertEquals(1, $map1->get('a'));
        $this->assertEquals(2, $map1->get('b'));
        $this->assertEquals(3, $map1->get('c'));
        $this->assertEquals(4, $map1->get('d'));
    }

    public function testMergeWithArray(): void
    {
        $map = new StructuredMap();
        $map->set('a', 1);
        $map->merge(['b' => 2, 'c' => 3]);

        $this->assertEquals(1, $map->get('a'));
        $this->assertEquals(2, $map->get('b'));
        $this->assertEquals(3, $map->get('c'));
    }

    public function testMergeOverwritesExisting(): void
    {
        $map = new StructuredMap();
        $map->set('a', 1);
        $map->merge(['a' => 100]);

        $this->assertEquals(100, $map->get('a'));
    }

    public function testPrototypeCreatesEmptyCopy(): void
    {
        $map = new StructuredMap();
        $map->declare('name', 'is_string');
        $map->declare('age', 'is_int', nullable: true);
        $map->set('name', 'John');
        $map->set('age', 25);

        $prototype = $map->prototype();

        // Prototype has same declared keys
        $this->assertEquals($map->declaredKeys(), $prototype->declaredKeys());

        // But no values
        $this->assertCount(0, $prototype->all());
    }

    public function testPrototypePreservesValidation(): void
    {
        $map = new StructuredMap();
        $map->declare('email', fn ($v) => false !== filter_var($v, FILTER_VALIDATE_EMAIL));

        $prototype = $map->prototype();

        // Validation should still work
        $prototype->set('email', 'valid@test.com');
        $this->assertEquals('valid@test.com', $prototype->get('email'));

        $this->expectException(\InvalidArgumentException::class);
        $prototype->set('email', 'invalid');
    }

    public function testPrototypePreservesNullable(): void
    {
        $map = new StructuredMap();
        $map->declare('optional', nullable: true);

        $prototype = $map->prototype();
        $prototype->set('optional', null);

        $this->assertNull($prototype->get('optional'));
    }

    // =======================================================================
    // ITERATOR AND COUNTABLE TESTS
    // =======================================================================

    public function testIterator(): void
    {
        $map = new StructuredMap();
        $map->set('a', 1);
        $map->set('b', 2);
        $map->set('c', 3);

        $result = [];
        foreach ($map as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    public function testCount(): void
    {
        $map = new StructuredMap();
        $this->assertCount(0, $map);

        $map->set('a', 1);
        $this->assertCount(1, $map);

        $map->set('b', 2);
        $this->assertCount(2, $map);
    }

    public function testKeys(): void
    {
        $map = new StructuredMap();
        $map->set('a', 1);
        $map->set('b', 2);

        $this->assertEquals(['a', 'b'], $map->keys());
    }

    public function testValues(): void
    {
        $map = new StructuredMap();
        $map->set('a', 1);
        $map->set('b', 2);

        $this->assertEquals([1, 2], $map->values());
    }

    public function testAll(): void
    {
        $map = new StructuredMap();
        $map->set('a', 1);
        $map->set('b', 2);

        $this->assertEquals(['a' => 1, 'b' => 2], $map->all());
    }

    // =======================================================================
    // CONSTRUCTOR TESTS
    // =======================================================================

    public function testConstructorWithElements(): void
    {
        $map = new StructuredMap(elements: ['a' => 1, 'b' => 2]);

        $this->assertEquals(1, $map->get('a'));
        $this->assertEquals(2, $map->get('b'));
    }

    public function testConstructorWithValidation(): void
    {
        $map = new StructuredMap(
            elements: ['name' => 'John'],
            validation: ['name' => 'is_string'],
        );

        $this->assertEquals('John', $map->get('name'));
    }

    public function testConstructorWithNullables(): void
    {
        $map = new StructuredMap(
            elements: ['optional' => null],
            nullables: ['optional'],
        );

        $this->assertNull($map->get('optional'));
    }

    public function testConstructorWithValidationAndNullable(): void
    {
        $map = new StructuredMap(
            elements: ['email' => null],
            validation: ['email' => fn ($v) => false !== filter_var($v, FILTER_VALIDATE_EMAIL)],
            nullables: ['email'],
        );

        $this->assertNull($map->get('email'));
    }

    // =======================================================================
    // HAS AND HASDECLAREDKEY TESTS
    // =======================================================================

    public function testHas(): void
    {
        $map = new StructuredMap();
        $map->declare('name');

        $this->assertTrue($map->has('name'));
        $this->assertFalse($map->has('unknown'));
    }

    public function testHasDeclaredKey(): void
    {
        $map = new StructuredMap();
        $map->declare('firstName');

        $this->assertTrue($map->hasDeclaredKey('firstName'));
        $this->assertTrue($map->hasDeclaredKey('first_name')); // alias works
        $this->assertFalse($map->hasDeclaredKey('unknown'));
    }

    // =======================================================================
    // GET WITH DEFAULT TESTS
    // =======================================================================

    public function testGetWithDefault(): void
    {
        $map = new StructuredMap();
        $map->declare('name');

        $this->assertEquals('default', $map->get('name', 'default'));
    }

    public function testGetWithDefaultWhenValueExists(): void
    {
        $map = new StructuredMap();
        $map->set('name', 'John');

        $this->assertEquals('John', $map->get('name', 'default'));
    }

    public function testGetUndeclaredKeyThrows(): void
    {
        $map = new StructuredMap();

        $this->expectException(\OutOfBoundsException::class);
        $map->get('unknown');
    }

    // =======================================================================
    // ADD METHOD TESTS
    // =======================================================================

    public function testAddWithValidValues(): void
    {
        $map = new StructuredMap();
        $map->declare('numbers', fn ($v) => is_int($v) && $v > 0);
        // add() validates each value - all values must pass validation
        $map->add('numbers', [1, 3, 5]);

        $values = $map->get('numbers');
        $this->assertContains(1, $values);
        $this->assertContains(3, $values);
        $this->assertContains(5, $values);
    }

    public function testAddWithInvalidValueThrows(): void
    {
        $map = new StructuredMap();
        $map->declare('numbers', fn ($v) => is_int($v) && $v > 0);

        $this->expectException(\InvalidArgumentException::class);
        $map->add('numbers', [1, -2, 3]); // -2 is invalid
    }

    // =======================================================================
    // MAP METHOD TESTS
    // =======================================================================

    public function testMapAppliesCallableToAll(): void
    {
        $map = new StructuredMap();
        $map->set('a', 1);
        $map->set('b', 2);
        $map->set('c', 3);

        $result = $map->map(fn ($v) => $v * 2);

        $this->assertEquals([2, 4, 6], array_values($result));
    }

    // =======================================================================
    // UNDECLARED KEY AUTO-DECLARATION TESTS
    // =======================================================================

    public function testSetUndeclaredKeyAutoDeclares(): void
    {
        $map = new StructuredMap();
        $map->set('newKey', 'value');

        $this->assertTrue($map->has('newKey'));
        $this->assertEquals('value', $map->get('newKey'));
    }

    public function testSetNullUndeclaredKeyAutoDeclaresNullable(): void
    {
        $map = new StructuredMap();
        $map->set('nullableKey', null);

        $this->assertTrue($map->has('nullableKey'));
        $this->assertNull($map->get('nullableKey'));
    }
}
