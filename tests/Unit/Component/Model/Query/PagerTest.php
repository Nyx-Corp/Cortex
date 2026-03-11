<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Model\Query;

use Cortex\Component\Model\Query\Pager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Component\Model\Query\Pager
 */
class PagerTest extends TestCase
{
    // =======================================================================
    // CONSTRUCTOR TESTS
    // =======================================================================

    public function testConstructorWithPage(): void
    {
        $pager = new Pager(1);

        $this->assertEquals(1, $pager->page);
        $this->assertEquals(20, $pager->nbPerPage); // default
    }

    public function testConstructorWithCustomNbPerPage(): void
    {
        $pager = new Pager(1, nbPerPage: 50);

        $this->assertEquals(1, $pager->page);
        $this->assertEquals(50, $pager->nbPerPage);
    }

    public function testPageNeverBelowOne(): void
    {
        $pager = new Pager(0);
        $this->assertEquals(1, $pager->page);

        $pager = new Pager(-5);
        $this->assertEquals(1, $pager->page);
    }

    // =======================================================================
    // OFFSET AND LIMIT CALCULATIONS
    // =======================================================================

    public function testOffsetAndLimitPage1(): void
    {
        $pager = new Pager(1, nbPerPage: 20);

        $this->assertEquals(0, $pager->offset);
        $this->assertEquals(20, $pager->limit);
    }

    public function testOffsetAndLimitPage2(): void
    {
        $pager = new Pager(2, nbPerPage: 20);

        $this->assertEquals(20, $pager->offset);
        $this->assertEquals(40, $pager->limit);
    }

    public function testOffsetAndLimitPage5(): void
    {
        $pager = new Pager(5, nbPerPage: 10);

        $this->assertEquals(40, $pager->offset);
        $this->assertEquals(50, $pager->limit);
    }

    /** @dataProvider offsetLimitProvider */
    public function testOffsetAndLimitCalculations(int $page, int $nbPerPage, int $expectedOffset, int $expectedLimit): void
    {
        $pager = new Pager($page, nbPerPage: $nbPerPage);

        $this->assertEquals($expectedOffset, $pager->offset);
        $this->assertEquals($expectedLimit, $pager->limit);
    }

    public static function offsetLimitProvider(): array
    {
        return [
            'page 1, 10 per page' => [1, 10, 0, 10],
            'page 2, 10 per page' => [2, 10, 10, 20],
            'page 3, 10 per page' => [3, 10, 20, 30],
            'page 1, 25 per page' => [1, 25, 0, 25],
            'page 4, 25 per page' => [4, 25, 75, 100],
            'page 1, 100 per page' => [1, 100, 0, 100],
            'page 10, 5 per page' => [10, 5, 45, 50],
        ];
    }

    // =======================================================================
    // BIND TESTS
    // =======================================================================

    public function testBindSetsNbRecords(): void
    {
        $pager = new Pager(1);
        $pager->bind(100);

        $this->assertEquals(100, $pager->nbRecords);
    }

    public function testBindOnlyOnce(): void
    {
        $pager = new Pager(1);
        $pager->bind(100);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already bound');
        $pager->bind(200);
    }

    public function testNbRecordsThrowsIfNotBound(): void
    {
        $pager = new Pager(1);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('bind()');
        $_ = $pager->nbRecords;
    }

    public function testBindResetsPageIfOutOfBounds(): void
    {
        $pager = new Pager(10, nbPerPage: 20); // offset = 180

        // Only 50 records available, so page 10 is out of bounds
        $pager->bind(50);

        $this->assertEquals(1, $pager->page);
        $this->assertEquals(0, $pager->offset);
    }

    public function testBindKeepsPageIfInBounds(): void
    {
        $pager = new Pager(3, nbPerPage: 20); // offset = 40

        // 100 records, page 3 is valid
        $pager->bind(100);

        $this->assertEquals(3, $pager->page);
        $this->assertEquals(40, $pager->offset);
    }

    // =======================================================================
    // getPages() TESTS
    // =======================================================================

    public function testGetPagesReturnsCorrectRange(): void
    {
        $pager = new Pager(1, nbPerPage: 10);
        $pager->bind(100);

        $pages = $pager->getPages();

        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $pages);
    }

    public function testGetPagesWithPartialLastPage(): void
    {
        $pager = new Pager(1, nbPerPage: 10);
        $pager->bind(95); // 10 pages, last page has 5 items

        $pages = $pager->getPages();

        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $pages);
    }

    public function testGetPagesWithSinglePage(): void
    {
        $pager = new Pager(1, nbPerPage: 10);
        $pager->bind(5);

        $pages = $pager->getPages();

        $this->assertEquals([1], $pages);
    }

    public function testGetPagesWithZeroRecords(): void
    {
        $pager = new Pager(1, nbPerPage: 10);
        $pager->bind(0);

        $pages = $pager->getPages();

        $this->assertEquals([1], $pages); // Always at least 1 page
    }

    public function testGetPagesThrowsIfNotBound(): void
    {
        $pager = new Pager(1);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('bind()');
        $pager->getPages();
    }

    /** @dataProvider pageCountProvider */
    public function testGetPagesCount(int $nbRecords, int $nbPerPage, int $expectedPageCount): void
    {
        $pager = new Pager(1, nbPerPage: $nbPerPage);
        $pager->bind($nbRecords);

        $this->assertCount($expectedPageCount, $pager->getPages());
    }

    public static function pageCountProvider(): array
    {
        return [
            '100 records, 10 per page' => [100, 10, 10],
            '101 records, 10 per page' => [101, 10, 11],
            '99 records, 10 per page' => [99, 10, 10],
            '1 record, 10 per page' => [1, 10, 1],
            '0 records, 10 per page' => [0, 10, 1],
            '50 records, 25 per page' => [50, 25, 2],
            '51 records, 25 per page' => [51, 25, 3],
        ];
    }

    // =======================================================================
    // CLONE TESTS
    // =======================================================================

    public function testCloneResetsBoundState(): void
    {
        $pager = new Pager(2, nbPerPage: 20);
        $pager->bind(100);

        $clone = clone $pager;

        // Clone should not be bound
        $this->expectException(\LogicException::class);
        $_ = $clone->nbRecords;
    }

    public function testCloneCanBeReBound(): void
    {
        $pager = new Pager(2, nbPerPage: 20);
        $pager->bind(100);

        $clone = clone $pager;
        $clone->bind(200); // Should not throw

        $this->assertEquals(200, $clone->nbRecords);
    }

    public function testClonePreservesPageAndNbPerPage(): void
    {
        $pager = new Pager(5, nbPerPage: 25);
        $pager->bind(500);

        $clone = clone $pager;

        $this->assertEquals(5, $clone->page);
        $this->assertEquals(25, $clone->nbPerPage);
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testVeryLargeNbRecords(): void
    {
        $pager = new Pager(1, nbPerPage: 100);
        $pager->bind(1000000);

        $this->assertEquals(1000000, $pager->nbRecords);
        $this->assertCount(10000, $pager->getPages());
    }

    public function testVeryLargePageNumber(): void
    {
        $pager = new Pager(1000, nbPerPage: 10);
        $pager->bind(50000);

        $this->assertEquals(1000, $pager->page);
        $this->assertEquals(9990, $pager->offset);
    }

    public function testNbPerPageOne(): void
    {
        $pager = new Pager(5, nbPerPage: 1);

        $this->assertEquals(4, $pager->offset);
        $this->assertEquals(5, $pager->limit);

        $pager->bind(10);
        $this->assertCount(10, $pager->getPages());
    }
}
