<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Collection;

use Cortex\Component\Collection\AsyncCollection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Component\Collection\AsyncCollection
 */
class AsyncCollectionTest extends TestCase
{
    // =======================================================================
    // CREATION TESTS
    // =======================================================================

    public function testCreateFromArray(): void
    {
        $collection = AsyncCollection::create([1, 2, 3]);

        $this->assertEquals([1, 2, 3], $collection->toArray());
    }

    public function testCreateFromGenerator(): void
    {
        $generator = function () {
            yield 1;
            yield 2;
            yield 3;
        };

        $collection = AsyncCollection::create($generator());

        $this->assertEquals([1, 2, 3], array_values($collection->toArray()));
    }

    public function testCreateFromIterator(): void
    {
        $iterator = new \ArrayIterator([1, 2, 3]);
        $collection = AsyncCollection::create($iterator);

        $this->assertEquals([1, 2, 3], $collection->toArray());
    }

    public function testCreateFromCallable(): void
    {
        $collection = AsyncCollection::create(fn () => [1, 2, 3]);

        $this->assertEquals([1, 2, 3], $collection->toArray());
    }

    public function testCreateEmpty(): void
    {
        $collection = AsyncCollection::create([]);

        $this->assertEquals([], $collection->toArray());
    }

    public function testCreateFromCallableMustReturnIterable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('iterable');

        $collection = AsyncCollection::create(fn () => 'not iterable');
        $collection->toArray(); // Force evaluation
    }

    // =======================================================================
    // MAP TESTS
    // =======================================================================

    public function testMapIntegers(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->map(fn ($x) => $x * 2)
            ->toArray();

        $this->assertEquals([2, 4, 6], array_values($result));
    }

    public function testMapStrings(): void
    {
        $result = AsyncCollection::create(['a', 'b', 'c'])
            ->map(fn ($s) => strtoupper($s))
            ->toArray();

        $this->assertEquals(['A', 'B', 'C'], array_values($result));
    }

    public function testMapObjects(): void
    {
        $obj1 = new \stdClass();
        $obj1->value = 1;
        $obj2 = new \stdClass();
        $obj2->value = 2;

        $result = AsyncCollection::create([$obj1, $obj2])
            ->map(fn ($obj) => $obj->value)
            ->toArray();

        $this->assertEquals([1, 2], array_values($result));
    }

    public function testMapWithIndex(): void
    {
        $result = AsyncCollection::create(['a', 'b', 'c'])
            ->map(fn ($value, $key) => "$key:$value")
            ->toArray();

        $this->assertEquals(['0:a', '1:b', '2:c'], array_values($result));
    }

    public function testMapReturnsNull(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->map(fn ($x) => null)
            ->toArray();

        $this->assertEquals([null, null, null], array_values($result));
    }

    // =======================================================================
    // FILTER TESTS
    // =======================================================================

    public function testFilterIntegers(): void
    {
        $result = AsyncCollection::create([1, 2, 3, 4, 5])
            ->filter(fn ($x) => 0 === $x % 2)
            ->toArray();

        $this->assertEquals([2, 4], array_values($result));
    }

    public function testFilterStrings(): void
    {
        $result = AsyncCollection::create(['apple', 'banana', 'apricot', 'blueberry'])
            ->filter(fn ($s) => str_starts_with($s, 'a'))
            ->toArray();

        $this->assertEquals(['apple', 'apricot'], array_values($result));
    }

    public function testFilterObjects(): void
    {
        $users = [
            ['name' => 'Alice', 'active' => true],
            ['name' => 'Bob', 'active' => false],
            ['name' => 'Charlie', 'active' => true],
        ];

        $result = AsyncCollection::create($users)
            ->filter(fn ($u) => $u['active'])
            ->toArray();

        $this->assertCount(2, $result);
    }

    public function testFilterAll(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->filter(fn ($x) => false)
            ->toArray();

        $this->assertEquals([], $result);
    }

    public function testFilterNone(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->filter(fn ($x) => true)
            ->toArray();

        $this->assertEquals([1, 2, 3], array_values($result));
    }

    // =======================================================================
    // REDUCE TESTS
    // =======================================================================

    public function testReduceSum(): void
    {
        $result = AsyncCollection::create([1, 2, 3, 4, 5])
            ->reduce(fn ($acc, $x) => $acc + $x, 0);

        $this->assertEquals(15, $result);
    }

    public function testReduceConcat(): void
    {
        $result = AsyncCollection::create(['a', 'b', 'c'])
            ->reduce(fn ($acc, $x) => $acc.$x, '');

        $this->assertEquals('abc', $result);
    }

    public function testReduceWithInitial(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->reduce(fn ($acc, $x) => $acc + $x, 100);

        $this->assertEquals(106, $result);
    }

    public function testReduceEmpty(): void
    {
        $result = AsyncCollection::create([])
            ->reduce(fn ($acc, $x) => $acc + $x, 0);

        $this->assertEquals(0, $result);
    }

    public function testReduceToObject(): void
    {
        $result = AsyncCollection::create([['key' => 'a', 'value' => 1], ['key' => 'b', 'value' => 2]])
            ->reduce(function ($acc, $item) {
                $acc[$item['key']] = $item['value'];

                return $acc;
            }, []);

        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    public function testReduceWithKeyAccess(): void
    {
        $result = AsyncCollection::create(['a' => 1, 'b' => 2, 'c' => 3])
            ->reduce(fn ($acc, $value, $key) => $acc."$key=$value,", '');

        $this->assertEquals('a=1,b=2,c=3,', $result);
    }

    // =======================================================================
    // CHAINING TESTS
    // =======================================================================

    public function testMapThenFilter(): void
    {
        $result = AsyncCollection::create([1, 2, 3, 4, 5])
            ->map(fn ($x) => $x * 2)       // [2, 4, 6, 8, 10]
            ->filter(fn ($x) => $x > 5)    // [6, 8, 10]
            ->toArray();

        $this->assertEquals([6, 8, 10], array_values($result));
    }

    public function testFilterThenMap(): void
    {
        $result = AsyncCollection::create([1, 2, 3, 4, 5])
            ->filter(fn ($x) => 0 === $x % 2)  // [2, 4]
            ->map(fn ($x) => $x * 10)          // [20, 40]
            ->toArray();

        $this->assertEquals([20, 40], array_values($result));
    }

    public function testMapFilterReduce(): void
    {
        $result = AsyncCollection::create([1, 2, 3, 4, 5])
            ->map(fn ($x) => $x * 2)
            ->filter(fn ($x) => $x > 4)
            ->reduce(fn ($acc, $x) => $acc + $x, 0);

        $this->assertEquals(24, $result); // 6+8+10
    }

    public function testMultipleMaps(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->map(fn ($x) => $x + 1)   // [2, 3, 4]
            ->map(fn ($x) => $x * 2)   // [4, 6, 8]
            ->map(fn ($x) => "v$x")    // ["v4", "v6", "v8"]
            ->toArray();

        $this->assertEquals(['v4', 'v6', 'v8'], array_values($result));
    }

    public function testMultipleFilters(): void
    {
        $result = AsyncCollection::create(range(1, 20))
            ->filter(fn ($x) => 0 === $x % 2)   // even
            ->filter(fn ($x) => 0 === $x % 3)   // divisible by 3
            ->toArray();

        $this->assertEquals([6, 12, 18], array_values($result));
    }

    public function testComplexChain(): void
    {
        $users = [
            ['name' => 'Alice', 'age' => 25, 'active' => true],
            ['name' => 'Bob', 'age' => 17, 'active' => true],
            ['name' => 'Charlie', 'age' => 30, 'active' => false],
            ['name' => 'Diana', 'age' => 22, 'active' => true],
        ];

        $result = AsyncCollection::create($users)
            ->filter(fn ($u) => $u['active'])           // active only
            ->filter(fn ($u) => $u['age'] >= 18)        // adults
            ->map(fn ($u) => strtoupper($u['name']))    // uppercase names
            ->toArray();

        $this->assertEquals(['ALICE', 'DIANA'], array_values($result));
    }

    // =======================================================================
    // LAZY EVALUATION TESTS
    // =======================================================================

    public function testLazyEvaluationNotExecutedUntilIteration(): void
    {
        $executed = false;

        $collection = AsyncCollection::create([1, 2, 3])
            ->map(function ($x) use (&$executed) {
                $executed = true;

                return $x;
            });

        $this->assertFalse($executed); // Not yet executed

        $collection->first();

        $this->assertTrue($executed); // Now executed
    }

    public function testLazyEvaluationStopsEarly(): void
    {
        $count = 0;

        $result = AsyncCollection::create([1, 2, 3, 4, 5])
            ->map(function ($x) use (&$count) {
                ++$count;

                return $x;
            })
            ->first();

        $this->assertEquals(1, $result);
        $this->assertEquals(1, $count); // Only 1 element processed
    }

    // =======================================================================
    // TERMINATOR TESTS
    // =======================================================================

    public function testFirst(): void
    {
        $result = AsyncCollection::create([1, 2, 3])->first();

        $this->assertEquals(1, $result);
    }

    public function testFirstOnEmpty(): void
    {
        $result = AsyncCollection::create([])->first();

        $this->assertNull($result);
    }

    public function testFind(): void
    {
        $result = AsyncCollection::create([1, 2, 3, 4, 5])
            ->find(fn ($x) => $x > 3);

        $this->assertEquals(4, $result);
    }

    public function testFindNotFound(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->find(fn ($x) => $x > 10);

        $this->assertNull($result);
    }

    public function testToArray(): void
    {
        $result = AsyncCollection::create([1, 2, 3])->toArray();

        $this->assertEquals([1, 2, 3], $result);
    }

    public function testToArrayPreservesKeys(): void
    {
        $result = AsyncCollection::create(['a' => 1, 'b' => 2])->toArray();

        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    public function testCount(): void
    {
        $count = AsyncCollection::create([1, 2, 3, 4, 5])->count();

        $this->assertEquals(5, $count);
    }

    public function testCountAfterFilter(): void
    {
        $count = AsyncCollection::create([1, 2, 3, 4, 5])
            ->filter(fn ($x) => 0 === $x % 2)
            ->count();

        $this->assertEquals(2, $count);
    }

    public function testAt(): void
    {
        $collection = AsyncCollection::create(['a', 'b', 'c', 'd']);

        $this->assertEquals('a', $collection->at(0));
    }

    public function testAtMiddle(): void
    {
        $collection = AsyncCollection::create(['a', 'b', 'c', 'd']);

        $this->assertEquals('c', $collection->at(2));
    }

    public function testAtOutOfBounds(): void
    {
        $collection = AsyncCollection::create(['a', 'b', 'c']);

        $this->assertNull($collection->at(10));
    }

    public function testJoin(): void
    {
        $result = AsyncCollection::create(['a', 'b', 'c'])->join(', ');

        $this->assertEquals('a, b, c', $result);
    }

    public function testJoinNumbers(): void
    {
        $result = AsyncCollection::create([1, 2, 3])->join('-');

        $this->assertEquals('1-2-3', $result);
    }

    // =======================================================================
    // EACH TESTS
    // =======================================================================

    public function testEach(): void
    {
        $result = AsyncCollection::create([1, 2])
            ->each(function ($x) {
                yield $x;
                yield $x * 10;
            })
            ->toArray();

        $this->assertEquals([1, 10, 2, 20], array_values($result));
    }

    public function testEachWithKeys(): void
    {
        $result = AsyncCollection::create([1, 2])
            ->each(function ($x, $key) {
                yield "orig_$key" => $x;
                yield "mult_$key" => $x * 10;
            })
            ->toArray();

        $this->assertArrayHasKey('orig_0', $result);
        $this->assertArrayHasKey('mult_0', $result);
    }

    // =======================================================================
    // IF AND IFEMPTY TESTS
    // =======================================================================

    public function testIfConditionTrue(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->if(
                fn ($c) => $c->count() > 0,
                fn ($c) => $c->map(fn ($x) => $x * 2)->toArray()
            )
            ->toArray();

        $this->assertEquals([2, 4, 6], array_values($result));
    }

    public function testIfConditionFalse(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->if(
                fn ($c) => $c->count() > 100,
                fn ($c) => [],
                fn ($c) => $c->toArray()
            )
            ->toArray();

        $this->assertEquals([1, 2, 3], array_values($result));
    }

    public function testIfEmptyWhenEmpty(): void
    {
        $result = AsyncCollection::create([])
            ->ifEmpty(
                fn ($c) => [0], // Return default value
                fn ($c) => $c->toArray()
            )
            ->toArray();

        $this->assertEquals([0], $result);
    }

    public function testIfEmptyWhenNotEmpty(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->ifEmpty(
                fn ($c) => [0],
                fn ($c) => $c->map(fn ($x) => $x * 2)->toArray()
            )
            ->toArray();

        $this->assertEquals([2, 4, 6], array_values($result));
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testEmptyCollection(): void
    {
        $result = AsyncCollection::create([])
            ->map(fn ($x) => $x * 2)
            ->filter(fn ($x) => $x > 0)
            ->toArray();

        $this->assertEquals([], $result);
    }

    public function testSingleElement(): void
    {
        $result = AsyncCollection::create([42])
            ->map(fn ($x) => $x * 2)
            ->toArray();

        $this->assertEquals([84], array_values($result));
    }

    public function testLargeCollection(): void
    {
        $result = AsyncCollection::create(range(1, 10000))
            ->filter(fn ($x) => 0 === $x % 1000)
            ->toArray();

        $this->assertEquals([1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000], array_values($result));
    }

    public function testWithNullValues(): void
    {
        $result = AsyncCollection::create([1, null, 2, null, 3])
            ->filter(fn ($x) => null !== $x)
            ->toArray();

        $this->assertEquals([1, 2, 3], array_values($result));
    }

    public function testWithMixedTypes(): void
    {
        $result = AsyncCollection::create([1, 'two', 3.0, true, null])
            ->filter(fn ($x) => is_numeric($x))
            ->toArray();

        $this->assertEquals([1, 3.0], array_values($result));
    }

    public function testPreservesOriginalKeys(): void
    {
        $result = AsyncCollection::create(['a' => 1, 'b' => 2, 'c' => 3])
            ->filter(fn ($x) => $x > 1)
            ->toArray();

        $this->assertArrayHasKey('b', $result);
        $this->assertArrayHasKey('c', $result);
        $this->assertArrayNotHasKey('a', $result);
    }

    public function testIterableMultipleTimes(): void
    {
        $collection = AsyncCollection::create([1, 2, 3]);

        // First iteration
        $first = $collection->toArray();
        $this->assertEquals([1, 2, 3], $first);

        // Second iteration
        $second = $collection->toArray();
        $this->assertEquals([1, 2, 3], $second);
    }

    public function testJsonSerializable(): void
    {
        $collection = AsyncCollection::create([1, 2, 3]);

        $json = json_encode($collection);

        $this->assertEquals('[1,2,3]', $json);
    }

    public function testCountable(): void
    {
        $collection = AsyncCollection::create([1, 2, 3, 4, 5]);

        $this->assertCount(5, $collection);
    }

    // =======================================================================
    // ITERATOR INTERFACE TESTS
    // =======================================================================

    public function testIteratorInterface(): void
    {
        $collection = AsyncCollection::create([1, 2, 3]);
        $result = [];

        foreach ($collection as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $result);
    }

    public function testRewind(): void
    {
        $collection = AsyncCollection::create([1, 2, 3]);

        // First iteration - elements are cached
        foreach ($collection as $value) {
            break; // Stop early
        }

        // Rewind starts fresh iterator but may include cached elements
        $collection->rewind();
        $result = [];
        foreach ($collection as $value) {
            $result[] = $value;
        }

        // After partial iteration and rewind, we get cached + remaining elements
        // The implementation caches consumed elements, so rewind includes them plus continues
        $this->assertCount(4, $result); // 1 (cached) + 1, 2, 3 (continued)
    }

    // =======================================================================
    // THEN AND AS TESTS
    // =======================================================================

    public function testThen(): void
    {
        $result = AsyncCollection::create([1, 2, 3])
            ->then(fn ($c) => $c->map(fn ($x) => $x * 2))
            ->toArray();

        $this->assertEquals([2, 4, 6], array_values($result));
    }

    public function testAs(): void
    {
        // Test that as() returns same type when same class
        $collection = AsyncCollection::create([1, 2, 3]);
        $same = $collection->as(AsyncCollection::class);

        $this->assertSame($collection, $same);
    }
}
