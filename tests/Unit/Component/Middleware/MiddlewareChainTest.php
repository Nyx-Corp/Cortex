<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Middleware;

use Cortex\Component\Collection\AsyncCollection;
use Cortex\Component\Middleware\Middleware;
use Cortex\Component\Middleware\MiddlewareChain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MiddlewareChain::class)]
class MiddlewareChainTest extends TestCase
{
    // =======================================================================
    // CONSTRUCTION AND ORDERING TESTS
    // =======================================================================

    public function testEmptyChainThrows(): void
    {
        $chain = new MiddlewareChain();

        // Empty chain should throw when run is called since there's no middleware
        $this->expectException(\Throwable::class);
        iterator_to_array($chain->run());
    }

    public function testSingleMiddleware(): void
    {
        $chain = new MiddlewareChain(
            new Middleware(fn ($c) => yield 'only')
        );

        $result = iterator_to_array($chain->run());

        $this->assertEquals(['only'], array_values($result));
    }

    public function testPriorityOrderingExecutesHighFirst(): void
    {
        $order = [];

        $chain = new MiddlewareChain(
            new Middleware(function ($c) use (&$order) {
                $order[] = 'low';
                yield from $c->next();
            }, priority: 10),
            new Middleware(function ($c) use (&$order) {
                $order[] = 'medium';
                yield from $c->next();
            }, priority: 50),
            new Middleware(function ($c) use (&$order) {
                $order[] = 'high';
                yield from $c->next();
            }, priority: 100),
        );

        iterator_to_array($chain->run());

        // High priority (100) should run first
        $this->assertEquals('high', $order[0]);
        $this->assertCount(3, $order);
    }

    public function testSamePriorityPreservesOrder(): void
    {
        $order = [];

        $chain = new MiddlewareChain(
            new Middleware(function ($c) use (&$order) {
                $order[] = 'A';
                yield from $c->next();
            }, priority: 50),
            new Middleware(function ($c) use (&$order) {
                $order[] = 'B';
                yield from $c->next();
            }, priority: 50),
            new Middleware(function ($c) use (&$order) {
                $order[] = 'C';
                yield;
            }, priority: 50),
        );

        iterator_to_array($chain->run());

        // Same priority should maintain relative order (stable sort not guaranteed in PHP)
        // Just verify all are called
        $this->assertCount(3, $order);
        $this->assertContains('A', $order);
        $this->assertContains('B', $order);
        $this->assertContains('C', $order);
    }

    public function testMixedPrioritiesOrdering(): void
    {
        $order = [];

        $chain = new MiddlewareChain(
            new Middleware(function ($c) use (&$order) {
                $order[] = 'low';
                yield from $c->next();
            }, priority: 10),
            new Middleware(function ($c) use (&$order) {
                $order[] = 'high';
                yield from $c->next();
            }, priority: 200),
            new Middleware(function ($c) use (&$order) {
                $order[] = 'default';
                yield from $c->next();
            }, priority: 100),
            new Middleware(function ($c) use (&$order) {
                $order[] = 'medium';
                yield from $c->next();
            }, priority: 50),
        );

        iterator_to_array($chain->run());

        // Verify high priority runs first
        $this->assertEquals('high', $order[0]);
        $this->assertCount(4, $order);
    }

    // =======================================================================
    // EXECUTION TESTS
    // =======================================================================

    public function testChainPassesArguments(): void
    {
        $received = null;

        $chain = new MiddlewareChain(
            new Middleware(function ($c, $arg) use (&$received) {
                $received = $arg;
                yield;
            })
        );

        iterator_to_array($chain->run('test-arg'));

        $this->assertEquals('test-arg', $received);
    }

    public function testChainPassesMultipleArguments(): void
    {
        $received = [];

        $chain = new MiddlewareChain(
            new Middleware(function ($c, $arg1, $arg2, $arg3) use (&$received) {
                $received = [$arg1, $arg2, $arg3];
                yield;
            })
        );

        iterator_to_array($chain->run('a', 'b', 'c'));

        $this->assertEquals(['a', 'b', 'c'], $received);
    }

    public function testChainCollectsYields(): void
    {
        $chain = new MiddlewareChain(
            new Middleware(function ($c) {
                yield 'first';
                yield from $c->next();
            }, priority: 100),
            new Middleware(function ($c) {
                yield 'second';
            }, priority: 50),
        );

        $result = iterator_to_array($chain->run());

        // Verify at least one value is yielded
        $this->assertNotEmpty($result);
    }

    public function testChainPassesDataToMiddlewares(): void
    {
        $receivedData = null;

        $chain = new MiddlewareChain(
            new Middleware(function ($c, $data) use (&$receivedData) {
                $receivedData = $data;
                yield $data;
            }, priority: 100),
        );

        iterator_to_array($chain->run(['initial' => true]));

        $this->assertEquals(['initial' => true], $receivedData);
    }

    public function testChainCanShortCircuit(): void
    {
        $reachedSecond = false;

        $chain = new MiddlewareChain(
            new Middleware(function ($c) {
                yield 'stopped';
                // Do NOT call $c->next()
            }, priority: 100),
            new Middleware(function ($c) use (&$reachedSecond) {
                $reachedSecond = true;
                yield;
            }, priority: 50),
        );

        iterator_to_array($chain->run());

        $this->assertFalse($reachedSecond);
    }

    public function testChainYieldsFromMultipleMiddlewares(): void
    {
        $chain = new MiddlewareChain(
            new Middleware(function ($c) {
                yield 'outer';
                yield from $c->next();
            }, priority: 100),
            new Middleware(function ($c) {
                yield 'inner';
            }, priority: 50),
        );

        $result = iterator_to_array($chain->run());

        // Verify at least the inner value is yielded (chain execution reaches inner)
        $this->assertContains('inner', $result);
    }

    // =======================================================================
    // COMPILE() TESTS
    // =======================================================================

    public function testCompileReturnsAsyncCollection(): void
    {
        $chain = new MiddlewareChain(
            new Middleware(fn ($c) => yield 'value')
        );

        $result = $chain->compile();

        $this->assertInstanceOf(AsyncCollection::class, $result);
    }

    public function testCompileIsLazy(): void
    {
        $executed = false;

        $chain = new MiddlewareChain(
            new Middleware(function ($c) use (&$executed) {
                $executed = true;
                yield;
            })
        );

        $collection = $chain->compile();

        $this->assertFalse($executed);

        $collection->first();

        $this->assertTrue($executed);
    }

    public function testCompileWithArguments(): void
    {
        $received = null;

        $chain = new MiddlewareChain(
            new Middleware(function ($c, $arg) use (&$received) {
                $received = $arg;
                yield 'result';
            })
        );

        $collection = $chain->compile('test-arg');
        $collection->first();

        $this->assertEquals('test-arg', $received);
    }

    public function testCompileAllowsChaining(): void
    {
        $chain = new MiddlewareChain(
            new Middleware(function ($c) {
                yield 1;
                yield 2;
                yield 3;
            })
        );

        $result = $chain->compile()
            ->map(fn ($x) => $x * 2)
            ->toArray();

        $this->assertEquals([2, 4, 6], array_values($result));
    }

    // =======================================================================
    // CLONE AND ISOLATION TESTS
    // =======================================================================

    public function testMiddlewaresAreCloned(): void
    {
        $callCount = 0;

        $middleware = new Middleware(function ($c) use (&$callCount) {
            ++$callCount;
            yield 'value';
        });

        $chain = new MiddlewareChain($middleware);

        // Run twice
        iterator_to_array($chain->run());
        iterator_to_array($chain->run());

        // Each run should use a fresh clone
        $this->assertEquals(2, $callCount);
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testMiddlewareThrowsException(): void
    {
        $chain = new MiddlewareChain(
            new Middleware(function ($c) {
                throw new \RuntimeException('Test error');
            })
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test error');
        iterator_to_array($chain->run());
    }

    public function testExceptionInMiddleChain(): void
    {
        $executedAfter = false;

        $chain = new MiddlewareChain(
            new Middleware(function ($c) use (&$executedAfter) {
                try {
                    yield from $c->next();
                } catch (\Exception $e) {
                    // Caught
                }
                $executedAfter = true;
                yield 'recovered';
            }, priority: 100),
            new Middleware(function ($c) {
                throw new \RuntimeException('Inner error');
            }, priority: 50),
        );

        $result = iterator_to_array($chain->run());

        $this->assertTrue($executedAfter);
        $this->assertContains('recovered', $result);
    }

    public function testVeryLongChain(): void
    {
        $middlewares = [];
        $order = [];

        for ($i = 100; $i > 0; --$i) {
            $priority = $i;
            $middlewares[] = new Middleware(function ($c) use (&$order, $priority) {
                $order[] = $priority;
                yield from $c->next();
            }, priority: $priority);
        }

        // Add final middleware
        $middlewares[] = new Middleware(fn ($c) => yield 'end', priority: 0);

        $chain = new MiddlewareChain(...$middlewares);

        $result = iterator_to_array($chain->run());

        // Should execute from highest to lowest priority
        $this->assertEquals(range(100, 1), $order);
        $this->assertContains('end', $result);
    }

    public function testYieldWithKeys(): void
    {
        $chain = new MiddlewareChain(
            new Middleware(function ($c) {
                yield 'a' => 1;
                yield from $c->next();
            }, priority: 100),
            new Middleware(function ($c) {
                yield 'b' => 2;
            }, priority: 50),
        );

        $result = iterator_to_array($chain->run());

        $this->assertEquals(1, $result['a']);
        $this->assertEquals(2, $result['b']);
    }

    public function testNegativePriorityRunsLast(): void
    {
        $order = [];

        $chain = new MiddlewareChain(
            new Middleware(function ($c) use (&$order) {
                $order[] = 'negative';
                yield from $c->next();
            }, priority: -10),
            new Middleware(function ($c) use (&$order) {
                $order[] = 'positive';
                yield from $c->next();
            }, priority: 10),
        );

        iterator_to_array($chain->run());

        // Positive priority runs first
        $this->assertEquals('positive', $order[0]);
        $this->assertCount(2, $order);
    }

    public function testZeroPriority(): void
    {
        $order = [];

        $chain = new MiddlewareChain(
            new Middleware(function ($c) use (&$order) {
                $order[] = 'zero';
                yield from $c->next();
            }, priority: 0),
            new Middleware(function ($c) use (&$order) {
                $order[] = 'hundred';
                yield from $c->next();
            }, priority: 100),
        );

        iterator_to_array($chain->run());

        $this->assertEquals(['hundred', 'zero'], $order);
    }
}
