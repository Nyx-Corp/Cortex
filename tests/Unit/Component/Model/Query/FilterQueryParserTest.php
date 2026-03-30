<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Model\Query;

use Cortex\Component\Model\Query\FilterQueryParser;
use Cortex\Component\Model\Query\ParsedFilterQuery;
use Cortex\Component\Model\Query\SortDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterQueryParser::class)]
final class FilterQueryParserTest extends TestCase
{
    // =======================================================================
    // EMPTY QUERY TESTS
    // =======================================================================

    #[Test]
    public function testEmptyStringReturnsDefaultsWithDefaultLimit(): void
    {
        $parser = new FilterQueryParser(defaultLimit: 10, maxLimit: 100);
        $result = $parser->parse('');

        $this->assertInstanceOf(ParsedFilterQuery::class, $result);
        $this->assertSame([], $result->filters);
        $this->assertNull($result->sort);
        $this->assertSame(10, $result->limit);
    }

    #[Test]
    public function testEmptyStringRespectCustomDefaultLimit(): void
    {
        $parser = new FilterQueryParser(defaultLimit: 20, maxLimit: 100);
        $result = $parser->parse('');

        $this->assertSame(20, $result->limit);
    }

    // =======================================================================
    // SIMPLE FILTER TESTS
    // =======================================================================

    #[Test]
    public function testSimpleFilterParsingType(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('type:page');

        $this->assertSame(['type' => 'page'], $result->filters);
        $this->assertNull($result->sort);
        $this->assertSame(10, $result->limit);
    }

    #[Test]
    public function testSimpleFilterParsingStatus(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('status:published');

        $this->assertSame(['status' => 'published'], $result->filters);
    }

    // =======================================================================
    // MULTIPLE FILTERS TESTS
    // =======================================================================

    #[Test]
    public function testMultipleFiltersAreCombined(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('type:page status:published');

        $this->assertSame(
            ['type' => 'page', 'status' => 'published'],
            $result->filters
        );
    }

    #[Test]
    public function testThreeFiltersAreCombined(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('type:page status:published author:john');

        $this->assertSame(
            ['type' => 'page', 'status' => 'published', 'author' => 'john'],
            $result->filters
        );
    }

    // =======================================================================
    // QUOTED VALUES TESTS
    // =======================================================================

    #[Test]
    public function testQuotedValueWithSpaces(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('name:"hello world"');

        $this->assertSame(['name' => 'hello world'], $result->filters);
    }

    #[Test]
    public function testQuotedValuePreservesInternalPunctuation(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('description:"Hello, world!"');

        $this->assertSame(['description' => 'Hello, world!'], $result->filters);
    }

    #[Test]
    public function testMixedQuotedAndUnquotedFilters(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('type:page name:"My Page"');

        $this->assertSame(
            ['type' => 'page', 'name' => 'My Page'],
            $result->filters
        );
    }

    // =======================================================================
    // SORT TESTS
    // =======================================================================

    #[Test]
    public function testSortAscendingExplicit(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('sort:name_asc');

        $this->assertSame([], $result->filters);
        $this->assertNotNull($result->sort);
        $this->assertSame('name', $result->sort->field);
        $this->assertSame(SortDirection::ASC, $result->sort->direction);
    }

    #[Test]
    public function testSortDescending(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('sort:name_desc');

        $this->assertNotNull($result->sort);
        $this->assertSame('name', $result->sort->field);
        $this->assertSame(SortDirection::DESC, $result->sort->direction);
    }

    #[Test]
    public function testSortWithoutDirectionDefaultsToAsc(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('sort:createdAt');

        $this->assertNotNull($result->sort);
        $this->assertSame('createdAt', $result->sort->field);
        $this->assertSame(SortDirection::ASC, $result->sort->direction);
    }

    #[Test]
    public function testSortFieldNameWithUnderscore(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('sort:created_at_desc');

        $this->assertNotNull($result->sort);
        $this->assertSame('created_at', $result->sort->field);
        $this->assertSame(SortDirection::DESC, $result->sort->direction);
    }

    // =======================================================================
    // LIMIT TESTS
    // =======================================================================

    #[Test]
    public function testLimitParsing(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('limit:5');

        $this->assertSame(5, $result->limit);
    }

    #[Test]
    public function testLimitOneCaps(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('limit:0');

        $this->assertSame(1, $result->limit);
    }

    #[Test]
    public function testLimitCapsByMaxLimit(): void
    {
        $parser = new FilterQueryParser(defaultLimit: 10, maxLimit: 100);
        $result = $parser->parse('limit:999');

        $this->assertSame(100, $result->limit);
    }

    #[Test]
    public function testLimitCapsByMaxLimitCustom(): void
    {
        $parser = new FilterQueryParser(defaultLimit: 10, maxLimit: 50);
        $result = $parser->parse('limit:999');

        $this->assertSame(50, $result->limit);
    }

    #[Test]
    public function testLimitNegativeValueCaps(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('limit:-10');

        $this->assertSame(1, $result->limit);
    }

    // =======================================================================
    // COMBINED TESTS
    // =======================================================================

    #[Test]
    public function testFilterAndSortAndLimit(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('type:page sort:name_desc limit:5');

        $this->assertSame(['type' => 'page'], $result->filters);
        $this->assertNotNull($result->sort);
        $this->assertSame('name', $result->sort->field);
        $this->assertSame(SortDirection::DESC, $result->sort->direction);
        $this->assertSame(5, $result->limit);
    }

    #[Test]
    public function testMultipleFiltersWithSort(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('type:page status:published sort:createdAt_desc');

        $this->assertSame(
            ['type' => 'page', 'status' => 'published'],
            $result->filters
        );
        $this->assertNotNull($result->sort);
        $this->assertSame('createdAt', $result->sort->field);
        $this->assertSame(SortDirection::DESC, $result->sort->direction);
    }

    #[Test]
    public function testMultipleFiltersWithLimitOnly(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('type:page author:jane limit:20');

        $this->assertSame(['type' => 'page', 'author' => 'jane'], $result->filters);
        $this->assertNull($result->sort);
        $this->assertSame(20, $result->limit);
    }

    #[Test]
    public function testComplexQueryWithQuotesFilterSortLimit(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('title:"The White Rabbit" status:active sort:published_at_desc limit:10');

        $this->assertSame(
            ['title' => 'The White Rabbit', 'status' => 'active'],
            $result->filters
        );
        $this->assertNotNull($result->sort);
        $this->assertSame('published_at', $result->sort->field);
        $this->assertSame(SortDirection::DESC, $result->sort->direction);
        $this->assertSame(10, $result->limit);
    }

    // =======================================================================
    // LIMIT OVERRIDE TESTS
    // =======================================================================

    #[Test]
    public function testDefaultLimitAppliedWhenNoLimitToken(): void
    {
        $parser = new FilterQueryParser(defaultLimit: 20, maxLimit: 100);
        $result = $parser->parse('type:page');

        $this->assertSame(20, $result->limit);
    }

    #[Test]
    public function testExplicitLimitOverridesDefault(): void
    {
        $parser = new FilterQueryParser(defaultLimit: 20, maxLimit: 100);
        $result = $parser->parse('type:page limit:5');

        $this->assertSame(5, $result->limit);
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    #[Test]
    public function testWhitespaceHandling(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('  type:page   status:published  ');

        $this->assertSame(['type' => 'page', 'status' => 'published'], $result->filters);
    }

    #[Test]
    public function testInvalidTokensAreIgnored(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('type:page invalid sort:name_desc');

        $this->assertSame(['type' => 'page'], $result->filters);
        $this->assertNotNull($result->sort);
    }

    #[Test]
    public function testSortIsOverriddenByLastOccurrence(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('sort:name_asc sort:createdAt_desc');

        $this->assertNotNull($result->sort);
        $this->assertSame('createdAt', $result->sort->field);
        $this->assertSame(SortDirection::DESC, $result->sort->direction);
    }

    #[Test]
    public function testLimitIsOverriddenByLastOccurrence(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('limit:5 limit:20');

        $this->assertSame(20, $result->limit);
    }

    #[Test]
    public function testDuplicateFiltersAreOverridden(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('type:page type:post');

        $this->assertSame(['type' => 'post'], $result->filters);
    }

    #[Test]
    public function testNumericFieldValues(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('id:123');

        $this->assertSame(['id' => '123'], $result->filters);
    }

    #[Test]
    public function testEmptyQuotedValue(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('name:""');

        $this->assertSame(['name' => '""'], $result->filters);
    }

    #[Test]
    public function testSorterToString(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('sort:name_desc');

        $this->assertNotNull($result->sort);
        $this->assertSame('name_desc', (string) $result->sort);
    }

    #[Test]
    public function testSorterToStringAscending(): void
    {
        $parser = new FilterQueryParser();
        $result = $parser->parse('sort:name_asc');

        $this->assertNotNull($result->sort);
        $this->assertSame('name_asc', (string) $result->sort);
    }
}
