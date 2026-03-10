<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Model\Query;

use Cortex\Component\Model\ModelCollection;
use Cortex\Component\Model\Query\ModelQuery;
use Cortex\ValueObject\RegisteredClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Component\Model\Query\ModelQuery
 */
class ModelQueryTest extends TestCase
{
    private function createQuery(): ModelQuery
    {
        return new ModelQuery(
            resolver: fn () => yield from [],
            modelCollectionClass: new RegisteredClass(ModelCollection::class),
        );
    }

    // =======================================================================
    // filterNull() TESTS
    // =======================================================================

    public function testFilterNullAddsField(): void
    {
        $query = $this->createQuery();
        $query->filterNull('archivedAt');

        $this->assertSame(['archivedAt'], $query->nullFields);
    }

    public function testFilterNullMultipleFields(): void
    {
        $query = $this->createQuery();
        $query->filterNull('archivedAt')->filterNull('deletedAt');

        $this->assertSame(['archivedAt', 'deletedAt'], $query->nullFields);
    }

    public function testFilterNullReturnsSelf(): void
    {
        $query = $this->createQuery();
        $result = $query->filterNull('archivedAt');

        $this->assertSame($query, $result);
    }

    public function testFilterNullDoesNotAffectRegularFilters(): void
    {
        $query = $this->createQuery();
        $query->filterNull('archivedAt');

        $this->assertCount(0, $query->filters);
    }

    // =======================================================================
    // filterNotNull() TESTS
    // =======================================================================

    public function testFilterNotNullAddsField(): void
    {
        $query = $this->createQuery();
        $query->filterNotNull('archivedAt');

        $this->assertSame(['archivedAt'], $query->notNullFields);
    }

    public function testFilterNotNullMultipleFields(): void
    {
        $query = $this->createQuery();
        $query->filterNotNull('archivedAt')->filterNotNull('deletedAt');

        $this->assertSame(['archivedAt', 'deletedAt'], $query->notNullFields);
    }

    public function testFilterNotNullReturnsSelf(): void
    {
        $query = $this->createQuery();
        $result = $query->filterNotNull('archivedAt');

        $this->assertSame($query, $result);
    }

    public function testFilterNotNullDoesNotAffectRegularFilters(): void
    {
        $query = $this->createQuery();
        $query->filterNotNull('archivedAt');

        $this->assertCount(0, $query->filters);
    }

    // =======================================================================
    // COMBINED TESTS
    // =======================================================================

    public function testNullAndNotNullFieldsAreIndependent(): void
    {
        $query = $this->createQuery();
        $query->filterNull('deletedAt')->filterNotNull('archivedAt');

        $this->assertSame(['deletedAt'], $query->nullFields);
        $this->assertSame(['archivedAt'], $query->notNullFields);
    }

    public function testFilterByStillWorksNormally(): void
    {
        $query = $this->createQuery();
        $query->filterBy('name', 'test');
        $query->filterNull('archivedAt');

        $this->assertSame('test', $query->filters->get('name'));
        $this->assertSame(['archivedAt'], $query->nullFields);
    }
}
