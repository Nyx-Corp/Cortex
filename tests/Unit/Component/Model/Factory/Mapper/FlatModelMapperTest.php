<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Model\Factory\Mapper;

use Cortex\Component\Model\Factory\Mapper\FlatModelMapper;
use Cortex\Component\Model\Factory\ModelPrototype;
use Cortex\ValueObject\RegisteredClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlatModelMapper::class)]
class FlatModelMapperTest extends TestCase
{
    // =======================================================================
    // CONSTRUCTOR PARAM MAPPING TESTS
    // =======================================================================

    public function testMapsDirectConstructorParam(): void
    {
        $mapper = new FlatModelMapper();
        $prototype = new ModelPrototype(new RegisteredClass(FlatStubModel::class));

        $result = $mapper->prototype($prototype, ['_default' => ['name' => 'Alice', 'age' => 30]]);

        $this->assertEquals('Alice', $result->constructors->get('name'));
        $this->assertEquals(30, $result->constructors->get('age'));
    }

    public function testMapsCamelToSnakeConstructorParam(): void
    {
        $mapper = new FlatModelMapper();
        $prototype = new ModelPrototype(new RegisteredClass(FlatStubSnakeModel::class));

        $result = $mapper->prototype($prototype, ['_default' => ['first_name' => 'Bob']]);

        $this->assertEquals('Bob', $result->constructors->get('firstName'));
    }

    public function testMapsSnakeToCamelConstructorParam(): void
    {
        $mapper = new FlatModelMapper();
        $prototype = new ModelPrototype(new RegisteredClass(FlatStubSnakeModel::class));

        $result = $mapper->prototype($prototype, ['_default' => ['firstName' => 'Charlie']]);

        $this->assertEquals('Charlie', $result->constructors->get('firstName'));
    }

    public function testIgnoresUnknownAttribute(): void
    {
        $mapper = new FlatModelMapper();
        $prototype = new ModelPrototype(new RegisteredClass(FlatStubModel::class));

        $result = $mapper->prototype($prototype, ['_default' => ['name' => 'Alice', 'unknown_field' => 'ignored']]);

        $this->assertEquals('Alice', $result->constructors->get('name'));
        $this->assertCount(0, $result->callbacks);
    }

    public function testCachesKeyMapOnSecondCall(): void
    {
        $mapper = new FlatModelMapper();

        $prototype1 = new ModelPrototype(new RegisteredClass(FlatStubModel::class));
        $mapper->prototype($prototype1, ['_default' => ['name' => 'First']]);

        $prototype2 = new ModelPrototype(new RegisteredClass(FlatStubModel::class));
        $result = $mapper->prototype($prototype2, ['_default' => ['name' => 'Second']]);

        $this->assertEquals('Second', $result->constructors->get('name'));
    }

    public function testCachesIgnoredKeysOnSecondCall(): void
    {
        $mapper = new FlatModelMapper();

        $prototype1 = new ModelPrototype(new RegisteredClass(FlatStubModel::class));
        $mapper->prototype($prototype1, ['_default' => ['unknown' => 'a']]);

        $prototype2 = new ModelPrototype(new RegisteredClass(FlatStubModel::class));
        $result = $mapper->prototype($prototype2, ['_default' => ['unknown' => 'b']]);

        // Should still return a valid prototype (unknown is silently ignored)
        $this->assertNotNull($result);
    }

    // =======================================================================
    // SETTABLE PROPERTY AUTO-HYDRATION TESTS
    // =======================================================================

    public function testHydratesSettablePropertyViaCallback(): void
    {
        $mapper = new FlatModelMapper();
        $prototype = new ModelPrototype(new RegisteredClass(FlatStubArchivableModel::class));

        $date = new \DateTimeImmutable('2024-01-15');
        $result = $mapper->prototype($prototype, ['_default' => ['title' => 'Test', 'archivedAt' => $date]]);

        $this->assertEquals('Test', $result->constructors->get('title'));
        $this->assertCount(1, $result->callbacks);
    }

    public function testSettablePropertyCallbackSetsValue(): void
    {
        $mapper = new FlatModelMapper();
        $prototype = new ModelPrototype(new RegisteredClass(FlatStubArchivableModel::class));

        $date = new \DateTimeImmutable('2024-01-15');
        $result = $mapper->prototype($prototype, ['_default' => ['title' => 'Test', 'archivedAt' => $date]]);

        // Simulate what ModelFactory::assemble() does
        $model = new FlatStubArchivableModel(title: 'Test');
        $result->callbacks->map(fn (callable $callback) => $callback($model));

        $this->assertSame($date, $model->archivedAt);
    }

    public function testCachesPropertyKeysOnSecondCall(): void
    {
        $mapper = new FlatModelMapper();

        $date1 = new \DateTimeImmutable('2024-01-01');
        $prototype1 = new ModelPrototype(new RegisteredClass(FlatStubArchivableModel::class));
        $mapper->prototype($prototype1, ['_default' => ['title' => 'A', 'archivedAt' => $date1]]);

        $date2 = new \DateTimeImmutable('2024-06-15');
        $prototype2 = new ModelPrototype(new RegisteredClass(FlatStubArchivableModel::class));
        $result = $mapper->prototype($prototype2, ['_default' => ['title' => 'B', 'archivedAt' => $date2]]);

        $model = new FlatStubArchivableModel(title: 'B');
        $result->callbacks->map(fn (callable $callback) => $callback($model));

        $this->assertSame($date2, $model->archivedAt);
    }

    public function testDoesNotHydratePrivateSetProperty(): void
    {
        $mapper = new FlatModelMapper();
        $prototype = new ModelPrototype(new RegisteredClass(FlatStubPrivateSetModel::class));

        $result = $mapper->prototype($prototype, ['_default' => ['name' => 'Test', 'internal' => 'hacked']]);

        $this->assertEquals('Test', $result->constructors->get('name'));
        $this->assertCount(0, $result->callbacks);
    }

    public function testDoesNotHydrateReadonlyProperty(): void
    {
        $mapper = new FlatModelMapper();
        $prototype = new ModelPrototype(new RegisteredClass(FlatStubReadonlyPropModel::class));

        $result = $mapper->prototype($prototype, ['_default' => ['name' => 'Test', 'label' => 'ignored']]);

        $this->assertEquals('Test', $result->constructors->get('name'));
        $this->assertCount(0, $result->callbacks);
    }

    public function testReturnsPrototypePreserved(): void
    {
        $mapper = new FlatModelMapper();
        $prototype = new ModelPrototype(new RegisteredClass(FlatStubModel::class));

        $result = $mapper->prototype($prototype, ['_default' => ['name' => 'Test']]);

        $this->assertNotNull($result);
        $this->assertEquals(FlatStubModel::class, (string) $result->modelClass);
    }
}

// =======================================================================
// TEST MODEL CLASSES
// =======================================================================

class FlatStubModel
{
    public function __construct(
        public readonly string $name,
        public readonly int $age = 0,
    ) {
    }
}

class FlatStubArchivableModel
{
    public ?\DateTimeInterface $archivedAt = null {
        set(?\DateTimeInterface $value) => $value;
    }

    public function __construct(
        public readonly string $title,
    ) {
    }
}

class FlatStubPrivateSetModel
{
    public private(set) string $internal = 'default';

    public function __construct(
        public readonly string $name,
    ) {
    }
}

class FlatStubReadonlyPropModel
{
    public readonly string $label;

    public function __construct(
        public readonly string $name,
    ) {
    }
}

class FlatStubSnakeModel
{
    public function __construct(
        public readonly string $firstName,
    ) {
    }
}
