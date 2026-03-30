<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Symfony\Serializer;

use Cortex\Bridge\Symfony\Serializer\RepresentationRegistry;
use Cortex\Component\Mapper\ArrayMapper;
use Cortex\Component\Mapper\ModelRepresentation;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Bridge\Symfony\Serializer\RepresentationRegistry
 */
class RepresentationRegistryTest extends TestCase
{
    // =======================================================================
    // REGISTER & GET
    // =======================================================================

    public function testRegisterAndGet(): void
    {
        $registry = new RepresentationRegistry();
        $representation = $this->createStub(ModelRepresentation::class);

        $registry->register('App\\Model\\Foo', $representation);

        $this->assertSame($representation, $registry->get('App\\Model\\Foo'));
    }

    public function testHasReturnsFalseForUnknown(): void
    {
        $registry = new RepresentationRegistry();

        $this->assertFalse($registry->has('App\\Model\\Unknown'));
    }

    public function testHasReturnsTrueAfterRegister(): void
    {
        $registry = new RepresentationRegistry();
        $registry->register('App\\Model\\Foo', $this->createStub(ModelRepresentation::class));

        $this->assertTrue($registry->has('App\\Model\\Foo'));
    }

    public function testGetThrowsForUnknown(): void
    {
        $registry = new RepresentationRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/No ModelRepresentation registered/');

        $registry->get('App\\Model\\Unknown');
    }

    // =======================================================================
    // STORE GROUP VALIDATION
    // =======================================================================

    public function testRequireStoreGroupPassesWhenGroupExists(): void
    {
        $registry = new RepresentationRegistry();

        $representation = $this->createMock(ModelRepresentation::class);
        $representation->method('groups')->willReturn([
            'store' => ['uuid', 'name'],
            'id' => ['uuid'],
        ]);

        $registry->register('App\\Model\\Foo', $representation);
        $registry->requireStoreGroup('App\\Model\\Foo');

        // Should not throw
        $this->assertSame($representation, $registry->get('App\\Model\\Foo'));
    }

    public function testRequireStoreGroupThrowsWhenMissing(): void
    {
        $registry = new RepresentationRegistry();

        $representation = $this->createMock(ModelRepresentation::class);
        $representation->method('groups')->willReturn([
            'id' => ['uuid'],
            'list' => ['uuid', 'name'],
        ]);

        $registry->register('App\\Model\\Foo', $representation);
        $registry->requireStoreGroup('App\\Model\\Foo');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/must define a "store" group/');

        $registry->get('App\\Model\\Foo');
    }

    public function testStoreValidationOnlyRunsOnce(): void
    {
        $registry = new RepresentationRegistry();

        $representation = $this->createMock(ModelRepresentation::class);
        $representation->expects($this->once())->method('groups')->willReturn([
            'store' => ['uuid'],
        ]);

        $registry->register('App\\Model\\Foo', $representation);
        $registry->requireStoreGroup('App\\Model\\Foo');

        $registry->get('App\\Model\\Foo');
        $registry->get('App\\Model\\Foo'); // groups() should not be called again
    }

    public function testNoStoreRequirementSkipsValidation(): void
    {
        $registry = new RepresentationRegistry();

        $representation = $this->createMock(ModelRepresentation::class);
        $representation->expects($this->never())->method('groups');

        $registry->register('App\\Model\\Foo', $representation);

        // No requireStoreGroup() call → no validation
        $registry->get('App\\Model\\Foo');
    }
}
