<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Model\Factory;

use Cortex\Component\Collection\StructuredMap;
use Cortex\Component\Model\Factory\ModelPrototype;
use Cortex\ValueObject\RegisteredClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Component\Model\Factory\ModelPrototype
 */
class ModelPrototypeTest extends TestCase
{
    // =======================================================================
    // CONSTRUCTION TESTS
    // =======================================================================

    public function testConstructWithSimpleClass(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(SimpleModel::class));

        $this->assertEquals(SimpleModel::class, (string) $prototype->modelClass);
    }

    public function testConstructWithConstructorParameters(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithConstructor::class));

        $declaredKeys = $prototype->constructors->declaredKeys();

        $this->assertContains('name', $declaredKeys);
        $this->assertContains('age', $declaredKeys);
    }

    public function testConstructWithNonInstantiableThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('instantiable');

        new ModelPrototype(new RegisteredClass(AbstractModel::class));
    }

    // =======================================================================
    // CONSTRUCTOR MAP TESTS
    // =======================================================================

    public function testConstructorMapHasStructuredMap(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithConstructor::class));

        $this->assertInstanceOf(StructuredMap::class, $prototype->constructors);
    }

    public function testConstructorMapValidatesStringType(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithConstructor::class));

        // Should accept string for name
        $prototype->constructors->set('name', 'John');
        $this->assertEquals('John', $prototype->constructors->get('name'));

        // Should reject non-string
        $this->expectException(\InvalidArgumentException::class);
        $prototype->constructors->set('name', 123);
    }

    public function testConstructorMapValidatesIntType(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithConstructor::class));

        // Should accept int for age
        $prototype->constructors->set('age', 25);
        $this->assertEquals(25, $prototype->constructors->get('age'));

        // Should also accept numeric strings (for query operators)
        $prototype->constructors->set('age', '>18');
        $this->assertEquals('>18', $prototype->constructors->get('age'));
    }

    public function testConstructorMapValidatesBoolType(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithBoolConstructor::class));

        // Should accept bool
        $prototype->constructors->set('active', true);
        $this->assertTrue($prototype->constructors->get('active'));

        // Should reject non-bool
        $this->expectException(\InvalidArgumentException::class);
        $prototype->constructors->set('active', 'yes');
    }

    public function testConstructorMapValidatesFloatType(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithFloatConstructor::class));

        // Should accept float
        $prototype->constructors->set('price', 19.99);
        $this->assertEquals(19.99, $prototype->constructors->get('price'));

        // Should accept numeric
        $prototype->constructors->set('price', 20);
        $this->assertEquals(20, $prototype->constructors->get('price'));

        // Should accept operator syntax
        $prototype->constructors->set('price', '>10.00');
        $this->assertEquals('>10.00', $prototype->constructors->get('price'));
    }

    public function testConstructorMapValidatesArrayType(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithArrayConstructor::class));

        // Should accept array
        $prototype->constructors->set('tags', ['a', 'b', 'c']);
        $this->assertEquals(['a', 'b', 'c'], $prototype->constructors->get('tags'));

        // Should reject non-array
        $this->expectException(\InvalidArgumentException::class);
        $prototype->constructors->set('tags', 'not-array');
    }

    public function testConstructorMapWithNullableParameter(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithNullableConstructor::class));

        // Should accept null
        $prototype->constructors->set('description', null);
        $this->assertNull($prototype->constructors->get('description'));

        // Should also accept string
        $prototype->constructors->set('description', 'text');
        $this->assertEquals('text', $prototype->constructors->get('description'));
    }

    public function testConstructorMapWithDefaultValue(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithDefaultConstructor::class));

        // Default value should be set
        $this->assertEquals('default', $prototype->constructors->get('status'));
    }

    public function testConstructorMapWithMixedType(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithMixedConstructor::class));

        // Should accept anything
        $prototype->constructors->set('data', 'string');
        $this->assertEquals('string', $prototype->constructors->get('data'));

        $prototype->constructors->set('data', 123);
        $this->assertEquals(123, $prototype->constructors->get('data'));

        $prototype->constructors->set('data', ['array']);
        $this->assertEquals(['array'], $prototype->constructors->get('data'));
    }

    public function testConstructorMapWithObjectType(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithObjectConstructor::class));

        $obj = new \stdClass();
        $prototype->constructors->set('dependency', $obj);

        $this->assertSame($obj, $prototype->constructors->get('dependency'));
    }

    public function testConstructorMapWithCustomClassType(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithDateConstructor::class));

        $date = new \DateTimeImmutable();
        $prototype->constructors->set('createdAt', $date);

        $this->assertSame($date, $prototype->constructors->get('createdAt'));
    }

    // =======================================================================
    // CALLBACKS MAP TESTS
    // =======================================================================

    public function testCallbacksMapIsEmpty(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(SimpleModel::class));

        $this->assertInstanceOf(StructuredMap::class, $prototype->callbacks);
        $this->assertCount(0, $prototype->callbacks);
    }

    // =======================================================================
    // MODEL CLASS PROPERTY TESTS
    // =======================================================================

    public function testModelClassCanBeChangedToSubclass(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(BaseModel::class));

        $prototype->modelClass = new RegisteredClass(ChildModel::class);

        $this->assertEquals(ChildModel::class, (string) $prototype->modelClass);
    }

    public function testModelClassCannotBeChangedToUnrelatedClass(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(BaseModel::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('subclass');

        $prototype->modelClass = new RegisteredClass(SimpleModel::class);
    }

    // =======================================================================
    // CLONE TESTS
    // =======================================================================

    public function testCloneCreatesNewInstance(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithConstructor::class));
        $prototype->constructors->set('name', 'Original');

        $clone = clone $prototype;
        $clone->constructors->set('name', 'Cloned');

        $this->assertEquals('Original', $prototype->constructors->get('name'));
        $this->assertEquals('Cloned', $clone->constructors->get('name'));
    }

    public function testClonePrototypesConstructors(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithConstructor::class));

        $clone = clone $prototype;

        // Clone should have same declared keys
        $this->assertEquals(
            $prototype->constructors->declaredKeys(),
            $clone->constructors->declaredKeys()
        );

        // But be separate instances
        $this->assertNotSame($prototype->constructors, $clone->constructors);
    }

    public function testClonePrototypesCallbacks(): void
    {
        $prototype = new ModelPrototype(new RegisteredClass(ModelWithConstructor::class));
        $prototype->callbacks->set('test', fn () => 'callback');

        $clone = clone $prototype;

        $this->assertNotSame($prototype->callbacks, $clone->callbacks);
    }
}

// =======================================================================
// TEST MODEL CLASSES
// =======================================================================

class SimpleModel
{
}

class ModelWithConstructor
{
    public function __construct(
        public string $name,
        public int $age,
    ) {
    }
}

class ModelWithBoolConstructor
{
    public function __construct(
        public bool $active,
    ) {
    }
}

class ModelWithFloatConstructor
{
    public function __construct(
        public float $price,
    ) {
    }
}

class ModelWithArrayConstructor
{
    public function __construct(
        public array $tags,
    ) {
    }
}

class ModelWithNullableConstructor
{
    public function __construct(
        public ?string $description,
    ) {
    }
}

class ModelWithDefaultConstructor
{
    public function __construct(
        public string $status = 'default',
    ) {
    }
}

class ModelWithMixedConstructor
{
    public function __construct(
        public mixed $data,
    ) {
    }
}

class ModelWithObjectConstructor
{
    public function __construct(
        public object $dependency,
    ) {
    }
}

class ModelWithDateConstructor
{
    public function __construct(
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

abstract class AbstractModel
{
}

class BaseModel
{
}

class ChildModel extends BaseModel
{
}
