<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Component\Mapper;

use Cortex\Component\Mapper\Relation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Relation::class)]
class RelationTest extends TestCase
{
    // =======================================================================
    // toUuid() TESTS
    // =======================================================================

    public function testToUuidCreatesRelation(): void
    {
        $relation = Relation::toUuid('organisation_uuid');

        $this->assertEquals('organisation_uuid', $relation->column);
        $this->assertFalse($relation->nullable);
        $this->assertEquals('uuid', $relation->property);
    }

    public function testToUuidWithNullable(): void
    {
        $relation = Relation::toUuid('parent_uuid', nullable: true);

        $this->assertEquals('parent_uuid', $relation->column);
        $this->assertTrue($relation->nullable);
        $this->assertEquals('uuid', $relation->property);
    }

    public function testToUuidDefaultsToNonNullable(): void
    {
        $relation = Relation::toUuid('target_uuid');

        $this->assertFalse($relation->nullable);
    }

    // =======================================================================
    // toModel() TESTS
    // =======================================================================

    public function testToModelCreatesRelation(): void
    {
        $relation = Relation::toModel('organisation');

        $this->assertEquals('organisation', $relation->column);
        $this->assertFalse($relation->nullable);
        $this->assertEquals('organisation', $relation->property);
    }

    public function testToModelPropertyMatchesColumn(): void
    {
        $relation = Relation::toModel('parent');

        $this->assertEquals($relation->column, $relation->property);
    }

    public function testToModelIsNeverNullable(): void
    {
        $relation = Relation::toModel('anything');

        $this->assertFalse($relation->nullable);
    }

    // =======================================================================
    // IMMUTABILITY TESTS
    // =======================================================================

    public function testRelationIsImmutable(): void
    {
        $relation = Relation::toUuid('test_uuid');

        $reflection = new \ReflectionClass($relation);

        $this->assertTrue($reflection->getProperty('column')->isReadOnly());
        $this->assertTrue($reflection->getProperty('nullable')->isReadOnly());
        $this->assertTrue($reflection->getProperty('property')->isReadOnly());
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testToUuidWithEmptyColumn(): void
    {
        $relation = Relation::toUuid('');

        $this->assertEquals('', $relation->column);
    }

    public function testToModelWithEmptyProperty(): void
    {
        $relation = Relation::toModel('');

        $this->assertEquals('', $relation->column);
        $this->assertEquals('', $relation->property);
    }

    public function testToUuidWithSpecialCharacters(): void
    {
        $relation = Relation::toUuid('organisation_uuid_v2');

        $this->assertEquals('organisation_uuid_v2', $relation->column);
    }

    public function testToModelWithCamelCase(): void
    {
        $relation = Relation::toModel('parentOrganisation');

        $this->assertEquals('parentOrganisation', $relation->column);
        $this->assertEquals('parentOrganisation', $relation->property);
    }
}
