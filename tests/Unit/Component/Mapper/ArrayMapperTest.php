<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Mapper;

use Cortex\Component\Mapper\ArrayMapper;
use Cortex\Component\Mapper\Relation;
use Cortex\Component\Mapper\Strategy;
use Cortex\Component\Mapper\Value;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Component\Mapper\ArrayMapper
 */
class ArrayMapperTest extends TestCase
{
    // =======================================================================
    // SIMPLE KEY MAPPING TESTS
    // =======================================================================

    public function testSimpleKeyRename(): void
    {
        $mapper = new ArrayMapper(['new_key' => 'oldKey']);
        $result = $mapper->map(['oldKey' => 'value']);

        $this->assertEquals('value', $result['new_key']);
    }

    public function testMultipleKeyRenames(): void
    {
        $mapper = new ArrayMapper([
            'first_name' => 'firstName',
            'last_name' => 'lastName',
        ]);

        $result = $mapper->map(['firstName' => 'John', 'lastName' => 'Doe']);

        $this->assertEquals('John', $result['first_name']);
        $this->assertEquals('Doe', $result['last_name']);
    }

    public function testKeyNotFoundSkipped(): void
    {
        $mapper = new ArrayMapper(['new_key' => 'nonExistent']);
        $result = $mapper->map(['existing' => 'value']);

        $this->assertArrayNotHasKey('new_key', $result);
        $this->assertEquals('value', $result['existing']);
    }

    public function testSameKeyMapping(): void
    {
        $mapper = new ArrayMapper(['name' => 'name']);
        $result = $mapper->map(['name' => 'John']);

        $this->assertEquals('John', $result['name']);
    }

    // =======================================================================
    // CALLBACK MAPPING TESTS
    // =======================================================================

    public function testCallbackTransformation(): void
    {
        $mapper = new ArrayMapper([
            'full_name' => fn ($value, $key) => strtoupper($value),
        ], sourceKeys: ['fullName']);

        $result = $mapper->map(['fullName' => 'John']);

        $this->assertEquals('JOHN', $result['full_name']);
    }

    public function testCallbackReturnsNull(): void
    {
        $mapper = new ArrayMapper([
            'nullable' => fn ($value, $key) => null,
        ], sourceKeys: ['nullable']);

        $result = $mapper->map(['nullable' => 'something']);

        $this->assertNull($result['nullable']);
    }

    public function testCallbackWithContext(): void
    {
        $mapper = new ArrayMapper([
            'processed' => fn ($value, $key, $extra) => $value . '-' . $extra,
        ], sourceKeys: ['processed']);

        // Context is passed as variadic arguments
        $dest = [];
        $result = $mapper->map(['processed' => 'base'], $dest, 'suffix');

        $this->assertEquals('base-suffix', $result['processed']);
    }

    // =======================================================================
    // AUTOMAPPING STRATEGY TESTS
    // =======================================================================

    public function testAutoMapCamelConvertsSnakeToCamel(): void
    {
        $mapper = new ArrayMapper([], format: Strategy::AutoMapCamel);

        $result = $mapper->map(['first_name' => 'John', 'last_name' => 'Doe']);

        $this->assertEquals('John', $result['firstName']);
        $this->assertEquals('Doe', $result['lastName']);
    }

    public function testAutoMapSnakeConvertsCamelToSnake(): void
    {
        $mapper = new ArrayMapper([], format: Strategy::AutoMapSnake);

        $result = $mapper->map(['firstName' => 'John', 'lastName' => 'Doe']);

        $this->assertEquals('John', $result['first_name']);
        $this->assertEquals('Doe', $result['last_name']);
    }

    public function testAutoMapCamelPreservesAlreadyCamel(): void
    {
        $mapper = new ArrayMapper([], format: Strategy::AutoMapCamel);

        $result = $mapper->map(['firstName' => 'John']);

        $this->assertEquals('John', $result['firstName']);
    }

    public function testAutoMapSnakePreservesAlreadySnake(): void
    {
        $mapper = new ArrayMapper([], format: Strategy::AutoMapSnake);

        $result = $mapper->map(['first_name' => 'John']);

        $this->assertEquals('John', $result['first_name']);
    }

    public function testAutoMapNoneOnlyMapsExplicitKeys(): void
    {
        $mapper = new ArrayMapper(
            ['mapped' => 'original'],
            automap: Strategy::AutoMapNone
        );

        $result = $mapper->map(['original' => 'value', 'extra' => 'ignored']);

        $this->assertArrayHasKey('mapped', $result);
        $this->assertArrayNotHasKey('extra', $result);
        $this->assertArrayNotHasKey('original', $result);
    }

    // =======================================================================
    // VALUE HANDLERS TESTS
    // =======================================================================

    public function testValueBoolFromIntOne(): void
    {
        $mapper = new ArrayMapper(['active' => Value::Bool]);
        $result = $mapper->map(['active' => 1]);

        $this->assertTrue($result['active']);
    }

    public function testValueBoolFromIntZero(): void
    {
        $mapper = new ArrayMapper(['active' => Value::Bool]);
        $result = $mapper->map(['active' => 0]);

        $this->assertFalse($result['active']);
    }

    public function testValueBoolFromBoolTrue(): void
    {
        $mapper = new ArrayMapper(['active' => Value::Bool]);
        $result = $mapper->map(['active' => true]);

        // When input is bool, output is int for database
        $this->assertEquals(1, $result['active']);
    }

    public function testValueBoolFromBoolFalse(): void
    {
        $mapper = new ArrayMapper(['active' => Value::Bool]);
        $result = $mapper->map(['active' => false]);

        $this->assertEquals(0, $result['active']);
    }

    public function testValueDateFromString(): void
    {
        $mapper = new ArrayMapper(['created_at' => Value::Date]);
        $result = $mapper->map(['createdAt' => '2024-01-15 10:30:00']);

        $this->assertInstanceOf(\DateTimeImmutable::class, $result['created_at']);
        $this->assertEquals('2024-01-15', $result['created_at']->format('Y-m-d'));
    }

    public function testValueDateFromDateTimeImmutable(): void
    {
        $mapper = new ArrayMapper(['created_at' => Value::Date]);
        $date = new \DateTimeImmutable('2024-01-15 10:30:00');
        $result = $mapper->map(['createdAt' => $date]);

        // When input is DateTimeImmutable, output is formatted string
        $this->assertIsString($result['created_at']);
        $this->assertStringContainsString('2024-01-15', $result['created_at']);
    }

    public function testValueDateFromNull(): void
    {
        $mapper = new ArrayMapper(['created_at' => Value::Date]);
        $result = $mapper->map(['createdAt' => null]);

        $this->assertNull($result['created_at']);
    }

    public function testValueDateFromEmptyString(): void
    {
        $mapper = new ArrayMapper(['created_at' => Value::Date]);
        $result = $mapper->map(['createdAt' => '']);

        $this->assertNull($result['created_at']);
    }

    public function testValueJsonEncodeArray(): void
    {
        $mapper = new ArrayMapper(['config' => Value::Json]);
        $result = $mapper->map(['config' => ['key' => 'value', 'nested' => ['a' => 1]]]);

        // Array input → JSON string output
        $this->assertIsString($result['config']);
        $this->assertJson($result['config']);
    }

    public function testValueJsonDecodeString(): void
    {
        $mapper = new ArrayMapper(['config' => Value::Json]);
        $result = $mapper->map(['config' => '{"key":"value"}']);

        // JSON string input → decoded array output
        $this->assertIsArray($result['config']);
        $this->assertEquals('value', $result['config']['key']);
    }

    public function testValueIgnoreRemovesKey(): void
    {
        $mapper = new ArrayMapper([
            'password' => Value::Ignore,
            'name' => 'name',
        ]);

        $result = $mapper->map(['password' => 'secret', 'name' => 'John']);

        $this->assertArrayNotHasKey('password', $result);
        $this->assertEquals('John', $result['name']);
    }

    // =======================================================================
    // BACKED ENUM MAPPING TESTS
    // =======================================================================

    public function testEnumFromValue(): void
    {
        $mapper = new ArrayMapper(['status' => TestStatus::class]);
        $result = $mapper->map(['status' => 'active']);

        $this->assertSame(TestStatus::Active, $result['status']);
    }

    public function testEnumToValue(): void
    {
        $mapper = new ArrayMapper(['status' => TestStatus::class]);
        $result = $mapper->map(['status' => TestStatus::Active]);

        $this->assertEquals('active', $result['status']);
    }

    // =======================================================================
    // RELATION MAPPING TESTS
    // =======================================================================

    public function testRelationToUuidFromObject(): void
    {
        $mapper = new ArrayMapper(['organisation' => Relation::toUuid('organisation_uuid')]);

        $organisation = new class {
            public string $uuid = '550e8400-e29b-41d4-a716-446655440000';
        };

        $result = $mapper->map(['organisation' => $organisation]);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $result['organisation_uuid']);
    }

    public function testRelationToUuidNullable(): void
    {
        $mapper = new ArrayMapper(['organisation' => Relation::toUuid('organisation_uuid', nullable: true)]);
        $result = $mapper->map(['organisation' => null]);

        $this->assertNull($result['organisation_uuid']);
    }

    public function testRelationToModelRenamesKey(): void
    {
        $mapper = new ArrayMapper(['organisation_uuid' => Relation::toModel('organisation')]);
        $result = $mapper->map(['organisation_uuid' => '550e8400-e29b-41d4-a716-446655440000']);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $result['organisation']);
    }

    // =======================================================================
    // OBJECT TO ARRAY CONVERSION TESTS
    // =======================================================================

    public function testMapStdClass(): void
    {
        $mapper = new ArrayMapper();
        $obj = new \stdClass();
        $obj->name = 'John';
        $obj->age = 30;

        $result = $mapper->map($obj);

        $this->assertEquals('John', $result['name']);
        $this->assertEquals(30, $result['age']);
    }

    public function testMapInvalidSourceThrows(): void
    {
        $mapper = new ArrayMapper();

        $this->expectException(\InvalidArgumentException::class);
        $mapper->map('not an array or object');
    }

    // =======================================================================
    // STRINGABLE AND JSON SERIALIZABLE TESTS
    // =======================================================================

    public function testStringableConverted(): void
    {
        $mapper = new ArrayMapper();
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringified';
            }
        };

        $result = $mapper->map(['value' => $stringable]);

        $this->assertEquals('stringified', $result['value']);
    }

    public function testBackedEnumConverted(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['status' => TestStatus::Active]);

        $this->assertEquals('active', $result['status']);
    }

    public function testJsonSerializableConverted(): void
    {
        $mapper = new ArrayMapper();
        $jsonable = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['key' => 'value'];
            }
        };

        $result = $mapper->map(['data' => $jsonable]);

        // JsonSerializable → JSON string
        $this->assertIsString($result['data']);
        $this->assertJson($result['data']);
    }

    // =======================================================================
    // DATA TYPES TESTS
    // =======================================================================

    public function testMapEmptyString(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['empty' => '']);

        $this->assertSame('', $result['empty']);
    }

    public function testMapUnicodeString(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['unicode' => '你好世界 🎉']);

        $this->assertEquals('你好世界 🎉', $result['unicode']);
    }

    public function testMapZero(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['zero' => 0]);

        $this->assertSame(0, $result['zero']);
    }

    public function testMapNegative(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['negative' => -100]);

        $this->assertSame(-100, $result['negative']);
    }

    public function testMapFloat(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['float' => 3.14159]);

        $this->assertSame(3.14159, $result['float']);
    }

    public function testMapNestedArray(): void
    {
        $mapper = new ArrayMapper();
        $nested = ['a' => ['b' => ['c' => 'deep']]];
        $result = $mapper->map(['nested' => $nested]);

        $this->assertEquals($nested, $result['nested']);
    }

    public function testMapEmptyArray(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['empty' => []]);

        $this->assertSame([], $result['empty']);
    }

    public function testMapNullValue(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['null' => null]);

        $this->assertNull($result['null']);
    }

    public function testMapBoolTrue(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['bool' => true]);

        $this->assertTrue($result['bool']);
    }

    public function testMapBoolFalse(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['bool' => false]);

        $this->assertFalse($result['bool']);
    }

    // =======================================================================
    // COMPLEX MAPPING TESTS
    // =======================================================================

    public function testMixedMappingAndCallbacks(): void
    {
        $mapper = new ArrayMapper([
            'id' => 'uuid',
            'is_active' => Value::Bool,
            'created_at' => Value::Date,
            'password' => Value::Ignore,
        ], format: Strategy::AutoMapSnake);

        $result = $mapper->map([
            'uuid' => '123',
            'is_active' => 1,
            'created_at' => '2024-01-15 10:30:00',
            'password' => 'secret',
            'extra_field' => 'kept',
        ]);

        $this->assertEquals('123', $result['id']);
        $this->assertTrue($result['is_active']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['created_at']);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertEquals('kept', $result['extra_field']);
    }

    public function testSourceKeysParameter(): void
    {
        $mapper = new ArrayMapper(
            [],
            sourceKeys: ['firstName', 'lastName'],
            format: Strategy::AutoMapSnake
        );

        $result = $mapper->map([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'extra' => 'value',
        ]);

        $this->assertEquals('John', $result['first_name']);
        $this->assertEquals('Doe', $result['last_name']);
        $this->assertEquals('value', $result['extra']);
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testEmptyMapping(): void
    {
        $mapper = new ArrayMapper();
        $result = $mapper->map(['a' => 1, 'b' => 2]);

        $this->assertEquals(1, $result['a']);
        $this->assertEquals(2, $result['b']);
    }

    public function testEmptySource(): void
    {
        $mapper = new ArrayMapper(['new' => 'old']);
        $result = $mapper->map([]);

        $this->assertEmpty($result);
    }

    public function testResultPassedByReference(): void
    {
        $mapper = new ArrayMapper();
        $existingResult = ['existing' => 'value'];

        $result = $mapper->map(['new' => 'data'], $existingResult);

        $this->assertEquals('value', $result['existing']);
        $this->assertEquals('data', $result['new']);
    }

    public function testNonMappableObjectThrows(): void
    {
        $mapper = new ArrayMapper();
        $unmappable = new class {
            private string $private = 'hidden';
        };

        $this->expectException(\InvalidArgumentException::class);
        $mapper->map(['obj' => $unmappable]);
    }
}

// Test enum for the tests
enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
