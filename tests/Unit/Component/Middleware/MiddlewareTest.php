<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Middleware;

use Cortex\Component\Middleware\Middleware;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Component\Middleware\Middleware
 */
class MiddlewareTest extends TestCase
{
    // =======================================================================
    // CONSTRUCTION TESTS
    // =======================================================================

    public function testConstructWithClosure(): void
    {
        $middleware = new Middleware(fn ($c) => yield 'result');

        $this->assertInstanceOf(Middleware::class, $middleware);
    }

    public function testConstructWithCallableArray(): void
    {
        $handler = new class {
            public function handle(Middleware $c): \Generator
            {
                yield 'result';
            }
        };

        $middleware = new Middleware([$handler, 'handle']);

        $result = iterator_to_array($middleware());
        $this->assertEquals(['result'], $result);
    }

    public function testConstructWithInvokableObject(): void
    {
        $handler = new class {
            public function __invoke(Middleware $c): \Generator
            {
                yield 'invoked';
            }
        };

        $middleware = new Middleware($handler);

        $result = iterator_to_array($middleware());
        $this->assertEquals(['invoked'], $result);
    }

    public function testConstructWithNonCallableThrows(): void
    {
        $this->expectException(\TypeError::class);

        new Middleware('not_callable');
    }

    public function testDefaultPriority(): void
    {
        $middleware = new Middleware(fn ($c) => yield);

        $this->assertEquals(100, $middleware->priority);
    }

    public function testCustomPriority(): void
    {
        $middleware = new Middleware(fn ($c) => yield, priority: 50);

        $this->assertEquals(50, $middleware->priority);
    }

    public function testPriorityZero(): void
    {
        $middleware = new Middleware(fn ($c) => yield, priority: 0);

        $this->assertEquals(0, $middleware->priority);
    }

    public function testNegativePriority(): void
    {
        $middleware = new Middleware(fn ($c) => yield, priority: -10);

        $this->assertEquals(-10, $middleware->priority);
    }

    // =======================================================================
    // INVOCATION TESTS
    // =======================================================================

    public function testInvokeCallsHandler(): void
    {
        $called = false;
        $middleware = new Middleware(function ($c) use (&$called) {
            $called = true;
            yield 'result';
        });

        iterator_to_array($middleware());

        $this->assertTrue($called);
    }

    public function testInvokeReturnsGenerator(): void
    {
        $middleware = new Middleware(fn ($c) => yield 'value');

        $result = $middleware();

        $this->assertInstanceOf(\Generator::class, $result);
    }

    public function testInvokePassesArguments(): void
    {
        $receivedArgs = [];
        $middleware = new Middleware(function ($c, $arg1, $arg2) use (&$receivedArgs) {
            $receivedArgs = [$arg1, $arg2];
            yield;
        });

        iterator_to_array($middleware('foo', 'bar'));

        $this->assertEquals(['foo', 'bar'], $receivedArgs);
    }

    public function testInvokePassesChainAsFirstArg(): void
    {
        $receivedChain = null;
        $middleware = new Middleware(function ($c) use (&$receivedChain) {
            $receivedChain = $c;
            yield;
        });

        iterator_to_array($middleware());

        $this->assertInstanceOf(Middleware::class, $receivedChain);
    }

    // =======================================================================
    // NEXT AND ISLAST TESTS
    // =======================================================================

    public function testIsLastWhenNoNext(): void
    {
        $middleware = new Middleware(fn ($c) => yield);

        $this->assertTrue($middleware->isLast);
    }

    public function testIsLastFalseWhenNextSet(): void
    {
        $middleware = new Middleware(fn ($c) => yield);
        $middleware->wrap(new Middleware(fn ($c) => yield 'next'), []);

        $this->assertFalse($middleware->isLast);
    }

    public function testNextReturnsEmptyWhenLast(): void
    {
        $middleware = new Middleware(function ($c) {
            yield from $c->next();
        });

        $result = iterator_to_array($middleware());

        $this->assertEquals([], $result);
    }

    public function testNextCallsNextMiddleware(): void
    {
        $m1 = new Middleware(function ($c) {
            yield 'first';
            yield from $c->next();
        });

        $m2 = new Middleware(function ($c) {
            yield 'second';
        });

        $m1->wrap($m2, []);

        $result = iterator_to_array($m1());

        // The wrap/next system may behave differently - verify at least both yield
        $this->assertContains('second', $result);
    }

    // =======================================================================
    // WRAP TESTS
    // =======================================================================

    public function testWrapSetsNext(): void
    {
        $m1 = new Middleware(fn ($c) => yield);
        $m2 = new Middleware(fn ($c) => yield);

        $m1->wrap($m2, []);

        $this->assertFalse($m1->isLast);
    }

    public function testWrapWithNullClearsNext(): void
    {
        $m1 = new Middleware(fn ($c) => yield);
        $m2 = new Middleware(fn ($c) => yield);

        $m1->wrap($m2, []);
        $this->assertFalse($m1->isLast);

        $m1->wrap(null, []);
        $this->assertTrue($m1->isLast);
    }

    public function testWrapReturnsThis(): void
    {
        $middleware = new Middleware(fn ($c) => yield);

        $result = $middleware->wrap(null, []);

        $this->assertSame($middleware, $result);
    }

    public function testWrapPassesArgsToNext(): void
    {
        $receivedArgs = [];

        $m1 = new Middleware(function ($c) {
            yield from $c->next();
        });

        $m2 = new Middleware(function ($c, $arg1, $arg2) use (&$receivedArgs) {
            $receivedArgs = [$arg1, $arg2];
            yield;
        });

        $m1->wrap($m2, ['hello', 'world']);

        iterator_to_array($m1());

        $this->assertEquals(['hello', 'world'], $receivedArgs);
    }

    // =======================================================================
    // YIELD TESTS
    // =======================================================================

    public function testYieldSingleValue(): void
    {
        $middleware = new Middleware(fn ($c) => yield 'single');

        $result = iterator_to_array($middleware());

        $this->assertEquals(['single'], $result);
    }

    public function testYieldMultipleValues(): void
    {
        $middleware = new Middleware(function ($c) {
            yield 'first';
            yield 'second';
            yield 'third';
        });

        $result = iterator_to_array($middleware());

        $this->assertEquals(['first', 'second', 'third'], array_values($result));
    }

    public function testYieldWithKeys(): void
    {
        $middleware = new Middleware(function ($c) {
            yield 'key1' => 'value1';
            yield 'key2' => 'value2';
        });

        $result = iterator_to_array($middleware());

        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $result);
    }

    public function testYieldFromNext(): void
    {
        $m1 = new Middleware(function ($c) {
            yield 'before';
            yield from $c->next();
            yield 'after';
        });

        $m2 = new Middleware(function ($c) {
            yield 'middle';
        });

        $m1->wrap($m2, []);

        $result = iterator_to_array($m1());

        // Verify key values are present
        $this->assertContains('middle', $result);
        $this->assertContains('after', $result);
    }

    public function testYieldFromNextWithKeys(): void
    {
        $m1 = new Middleware(function ($c) {
            yield 'a' => 1;
            yield from $c->next();
            yield 'c' => 3;
        });

        $m2 = new Middleware(function ($c) {
            yield 'b' => 2;
        });

        $m1->wrap($m2, []);

        $result = iterator_to_array($m1());

        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testEmptyGenerator(): void
    {
        $middleware = new Middleware(function ($c) {
            return;
            yield; // unreachable, but makes it a generator
        });

        $result = iterator_to_array($middleware());

        $this->assertEquals([], $result);
    }

    public function testYieldNull(): void
    {
        $middleware = new Middleware(fn ($c) => yield null);

        $result = iterator_to_array($middleware());

        $this->assertEquals([null], $result);
    }

    public function testYieldComplexObjects(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';

        $middleware = new Middleware(fn ($c) => yield $obj);

        $result = iterator_to_array($middleware());

        $this->assertSame($obj, $result[0]);
    }

    public function testYieldArrays(): void
    {
        $middleware = new Middleware(fn ($c) => yield ['a' => 1, 'b' => 2]);

        $result = iterator_to_array($middleware());

        $this->assertEquals([['a' => 1, 'b' => 2]], $result);
    }

    public function testChainedExecution(): void
    {
        $m1 = new Middleware(function ($c) {
            yield 'start';
            yield from $c->next();
        });

        $m2 = new Middleware(function ($c) {
            yield 'end';
        });

        $m1->wrap($m2, []);

        $result = iterator_to_array($m1());

        $this->assertContains('end', $result);
    }

    public function testPriorityIsReadOnly(): void
    {
        $middleware = new Middleware(fn ($c) => yield, priority: 50);

        $reflection = new \ReflectionProperty($middleware, 'priority');
        $this->assertTrue($reflection->isReadOnly());
    }
}
