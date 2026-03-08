<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Symfony\Api;

use Cortex\Bridge\Symfony\Api\VersionTransformerCollection;
use Cortex\Bridge\Symfony\Api\VersionTransformerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Bridge\Symfony\Api\VersionTransformerCollection
 */
class VersionTransformerCollectionTest extends TestCase
{
    // =======================================================================
    // CONSTRUCTION TESTS
    // =======================================================================

    public function testAcceptsEmptyIterable(): void
    {
        $collection = new VersionTransformerCollection([]);

        $this->assertInstanceOf(VersionTransformerCollection::class, $collection);
    }

    public function testAcceptsIterableOfTransformers(): void
    {
        $transformer = $this->createTransformer('App\\Command\\Foo');
        $collection = new VersionTransformerCollection([$transformer]);

        $this->assertInstanceOf(VersionTransformerCollection::class, $collection);
    }

    // =======================================================================
    // transformRequest() TESTS
    // =======================================================================

    public function testTransformRequestPassesThroughWhenNoTransformer(): void
    {
        $collection = new VersionTransformerCollection([]);
        $data = ['name' => 'test', 'email' => 'a@b.com'];

        $result = $collection->transformRequest('App\\Unknown\\Command', $data, 1);

        $this->assertSame($data, $result);
    }

    public function testTransformRequestDelegatesToMatchingTransformer(): void
    {
        $transformer = $this->createTransformer('App\\Command\\Foo');
        $transformer->method('transformRequest')
            ->with(['old_field' => 'value'], 1)
            ->willReturn(['new_field' => 'value']);

        $collection = new VersionTransformerCollection([$transformer]);

        $result = $collection->transformRequest('App\\Command\\Foo', ['old_field' => 'value'], 1);

        $this->assertEquals(['new_field' => 'value'], $result);
    }

    public function testTransformRequestIgnoresNonMatchingTransformers(): void
    {
        $transformer = $this->createTransformer('App\\Command\\Bar');
        $transformer->expects($this->never())->method('transformRequest');

        $collection = new VersionTransformerCollection([$transformer]);
        $data = ['name' => 'test'];

        $result = $collection->transformRequest('App\\Command\\Foo', $data, 1);

        $this->assertSame($data, $result);
    }

    public function testTransformRequestPassesVersionToTransformer(): void
    {
        $transformer = $this->createTransformer('App\\Command\\Foo');
        $transformer->expects($this->once())
            ->method('transformRequest')
            ->with($this->anything(), 3)
            ->willReturnArgument(0);

        $collection = new VersionTransformerCollection([$transformer]);
        $collection->transformRequest('App\\Command\\Foo', ['x' => 1], 3);
    }

    // =======================================================================
    // transformResponse() TESTS
    // =======================================================================

    public function testTransformResponsePassesThroughWhenNoTransformer(): void
    {
        $collection = new VersionTransformerCollection([]);
        $data = ['uuid' => 'abc-123', 'name' => 'test'];

        $result = $collection->transformResponse('App\\Unknown\\Command', $data, 1);

        $this->assertSame($data, $result);
    }

    public function testTransformResponseDelegatesToMatchingTransformer(): void
    {
        $transformer = $this->createTransformer('App\\Command\\Foo');
        $transformer->method('transformResponse')
            ->with(['full_name' => 'John Doe'], 1)
            ->willReturn(['name' => 'John Doe']);

        $collection = new VersionTransformerCollection([$transformer]);

        $result = $collection->transformResponse('App\\Command\\Foo', ['full_name' => 'John Doe'], 1);

        $this->assertEquals(['name' => 'John Doe'], $result);
    }

    public function testTransformResponsePassesVersionToTransformer(): void
    {
        $transformer = $this->createTransformer('App\\Command\\Foo');
        $transformer->expects($this->once())
            ->method('transformResponse')
            ->with($this->anything(), 2)
            ->willReturnArgument(0);

        $collection = new VersionTransformerCollection([$transformer]);
        $collection->transformResponse('App\\Command\\Foo', ['x' => 1], 2);
    }

    // =======================================================================
    // MULTIPLE TRANSFORMERS TESTS
    // =======================================================================

    public function testMultipleTransformersRoutedByCommandClass(): void
    {
        $fooTransformer = $this->createTransformer('App\\Command\\Foo');
        $fooTransformer->method('transformRequest')
            ->willReturn(['foo' => 'transformed']);

        $barTransformer = $this->createTransformer('App\\Command\\Bar');
        $barTransformer->method('transformRequest')
            ->willReturn(['bar' => 'transformed']);

        $collection = new VersionTransformerCollection([$fooTransformer, $barTransformer]);

        $this->assertEquals(
            ['foo' => 'transformed'],
            $collection->transformRequest('App\\Command\\Foo', [], 1)
        );
        $this->assertEquals(
            ['bar' => 'transformed'],
            $collection->transformRequest('App\\Command\\Bar', [], 1)
        );
    }

    public function testLastTransformerWinsForSameCommandClass(): void
    {
        $first = $this->createTransformer('App\\Command\\Foo');
        $first->method('transformRequest')->willReturn(['first' => true]);

        $second = $this->createTransformer('App\\Command\\Foo');
        $second->method('transformRequest')->willReturn(['second' => true]);

        $collection = new VersionTransformerCollection([$first, $second]);

        $this->assertEquals(
            ['second' => true],
            $collection->transformRequest('App\\Command\\Foo', [], 1)
        );
    }

    // =======================================================================
    // HELPERS
    // =======================================================================

    private function createTransformer(string $commandClass): VersionTransformerInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $mock = $this->createMock(VersionTransformerInterface::class);
        $mock->method('getCommandClass')->willReturn($commandClass);

        return $mock;
    }
}
