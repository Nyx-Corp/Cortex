<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Doctrine;

use Cortex\Bridge\Doctrine\DbalMappingConfiguration;
use Cortex\Bridge\Doctrine\JoinDefinition;
use Cortex\Component\Model\Factory\ModelFactory;
use Cortex\Component\Model\Query\Factory\QueryFactory;
use Cortex\Component\Model\Query\ModelQuery;
use PHPUnit\Framework\TestCase;

// Test fixtures - reuse from DbalAdapterTest
class TestOrg
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?TestOrg $parent = null,
    ) {}
}

class TestClubModel
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?TestOrg $organisation = null,
    ) {}
}

/**
 * @covers \Cortex\Bridge\Doctrine\JoinDefinition
 */
class JoinDefinitionTest extends TestCase
{
    private function createMockFactory(string $modelClass): ModelFactory
    {
        $mockModelQuery = $this->createMock(ModelQuery::class);
        $mockModelQuery->method('filterBy')->willReturnSelf();
        $mockModelQuery->method('first')->willReturn(null);

        $queryFactory = $this->createMock(QueryFactory::class);
        $queryFactory->method('createQuery')->willReturn($mockModelQuery);

        return new ModelFactory($modelClass, $queryFactory);
    }

    // =======================================================================
    // CONVENTION-BASED LOCAL KEY TESTS
    // =======================================================================

    public function testGetLocalKeyWithExplicitValue(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
        );

        $join = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            localKey: 'custom_org_uuid', // Explicit override
            alias: 'explicit_key',
        );

        // withRelationName should NOT override explicit localKey
        $joinWithRelation = $join->withRelationName('organisation');

        $this->assertEquals('custom_org_uuid', $joinWithRelation->getLocalKey());
    }

    public function testGetLocalKeyDeducedFromRelationName(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
        );

        $join = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            // No localKey - should use convention
            alias: 'convention_key',
        );

        // withRelationName provides the relation name for convention
        $joinWithRelation = $join->withRelationName('organisation');

        $this->assertEquals('organisation_uuid', $joinWithRelation->getLocalKey());
    }

    public function testGetLocalKeyWithDifferentRelationNames(): void
    {
        $factory = $this->createMockFactory(TestOrg::class);
        $config = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
        );

        $testCases = [
            'contact' => 'contact_uuid',
            'club' => 'club_uuid',
            'meeting' => 'meeting_uuid',
            'parent' => 'parent_uuid',
            'event' => 'event_uuid',
        ];

        foreach ($testCases as $relationName => $expectedLocalKey) {
            $join = (new JoinDefinition(
                factory: $factory,
                joinConfig: $config,
                alias: 'test_' . $relationName,
            ))->withRelationName($relationName);

            $this->assertEquals(
                $expectedLocalKey,
                $join->getLocalKey(),
                "Failed for relation: $relationName"
            );
        }
    }

    // =======================================================================
    // COLUMN AUTO-DETECTION TESTS (RELATION FK COLUMNS)
    // =======================================================================

    public function testColumnAutoDetectsRelationForeignKey(): void
    {
        // TestOrg has a 'parent' property which is a relation (TestOrg)
        // The Organisation config has a 'parent' join
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfigWithParent = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
            joins: [
                'parent' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: new DbalMappingConfiguration(
                        table: 'contact_organisation',
                    ),
                    alias: 'parent_org',
                ),
            ],
        );

        // Create a join using the config that has 'parent' in joins
        $join = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfigWithParent,
            alias: 'autodetect_fk',
        );

        $columns = $join->getColumns();

        // 'parent' should be mapped to 'parent_uuid' because it's a relation
        $this->assertContains('parent_uuid', $columns);
        // 'uuid' and 'name' should remain as-is
        $this->assertContains('uuid', $columns);
        $this->assertContains('name', $columns);
    }

    public function testColumnWithExplicitOverrideTakesPrecedence(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
            joins: [
                'parent' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: new DbalMappingConfiguration(
                        table: 'contact_organisation',
                    ),
                    alias: 'parent_override',
                ),
            ],
        );

        // Explicit columnOverrides should take precedence over auto-detection
        $join = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            alias: 'override_test',
            columnOverrides: ['parent' => 'custom_parent_fk'], // Override the convention
        );

        $columns = $join->getColumns();

        // Should use the explicit override, not the convention
        $this->assertContains('custom_parent_fk', $columns);
        $this->assertNotContains('parent_uuid', $columns);
        $this->assertNotContains('parent', $columns);
    }

    public function testColumnClassTypeDetectedAsRelationEvenWithoutJoins(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);

        // Config with NO joins - but 'parent' is still detected as relation via reflection
        $orgConfigNoJoins = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
            joins: [], // No joins, but reflection detects class types
        );

        $join = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfigNoJoins,
            alias: 'no_relation',
        );

        $columns = $join->getColumns();

        // 'parent' is of type TestOrg (a class), so it's detected as a relation
        // and mapped to 'parent_uuid'
        $this->assertContains('parent_uuid', $columns);
        $this->assertNotContains('parent', $columns);
    }

    // =======================================================================
    // WITH RELATION NAME METHOD TESTS
    // =======================================================================

    public function testWithRelationNamePreservesAllProperties(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
        );

        $original = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            localKey: 'explicit_key',
            alias: 'preserve_test',
            columnOverrides: ['foo' => 'bar'],
        );

        $withRelation = $original->withRelationName('test');

        // All properties should be preserved
        $this->assertSame($original->factory, $withRelation->factory);
        $this->assertSame($original->joinConfig, $withRelation->joinConfig);
        $this->assertEquals('explicit_key', $withRelation->localKey);
        $this->assertEquals($original->type, $withRelation->type);
        $this->assertEquals($original->getAlias(), $withRelation->getAlias());
    }

    public function testWithRelationNameCreatesNewInstance(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
        );

        $original = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            alias: 'instance_test',
        );

        $withRelation = $original->withRelationName('org');

        // Should be a different instance
        $this->assertNotSame($original, $withRelation);
    }

    // =======================================================================
    // WITH PARENT ALIAS PRESERVES RELATION NAME
    // =======================================================================

    public function testWithParentAliasPreservesRelationName(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
        );

        $original = (new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            alias: 'base',
        ))->withRelationName('organisation');

        // getLocalKey before withParentAlias
        $this->assertEquals('organisation_uuid', $original->getLocalKey());

        // Apply withParentAlias
        $nested = $original->withParentAlias('parent');

        // relationName should be preserved
        $this->assertEquals('organisation_uuid', $nested->getLocalKey());
        $this->assertEquals('parent_base', $nested->getAlias());
    }

    // =======================================================================
    // TO SQL USES GET LOCAL KEY
    // =======================================================================

    public function testToSqlUsesGetLocalKeyWithConvention(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
            primaryKey: 'uuid',
        );

        $join = (new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            // No localKey - uses convention
            alias: 'sql_test',
        ))->withRelationName('organisation');

        $sql = $join->toSql('main_table');

        // Should use the convention-derived localKey
        $this->assertStringContainsString('main_table.organisation_uuid', $sql);
        $this->assertStringContainsString('sql_test.uuid', $sql);
    }

    public function testToSqlUsesExplicitLocalKey(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
            primaryKey: 'uuid',
        );

        $join = (new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            localKey: 'custom_fk_column', // Explicit
            alias: 'sql_explicit',
        ))->withRelationName('organisation');

        $sql = $join->toSql('main_table');

        // Should use the explicit localKey, not the convention
        $this->assertStringContainsString('main_table.custom_fk_column', $sql);
        $this->assertStringNotContainsString('main_table.organisation_uuid', $sql);
    }

    // =======================================================================
    // DBAL MAPPING CONFIGURATION INJECTS RELATION NAME
    // =======================================================================

    public function testDbalMappingConfigurationInjectsRelationName(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
        );

        $clubFactory = $this->createMockFactory(TestClubModel::class);

        // Create config with joins - relationName should be injected
        $clubConfig = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClubModel::class,
            joins: [
                'organisation' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfig,
                    // No localKey - will use convention via injected relationName
                    alias: 'inject_test',
                ),
            ],
        );

        // Get the join from the processed config
        $processedJoin = $clubConfig->joins['organisation'];

        // The relationName should be injected, so getLocalKey() uses convention
        $this->assertEquals('organisation_uuid', $processedJoin->getLocalKey());
    }

    public function testDbalMappingConfigurationPreservesExplicitLocalKey(): void
    {
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
        );

        $clubFactory = $this->createMockFactory(TestClubModel::class);

        // Create config with explicit localKey
        $clubConfig = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClubModel::class,
            joins: [
                'organisation' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfig,
                    localKey: 'explicit_org_uuid', // Explicit
                    alias: 'explicit_inject',
                ),
            ],
        );

        // Get the join from the processed config
        $processedJoin = $clubConfig->joins['organisation'];

        // Explicit localKey should be preserved
        $this->assertEquals('explicit_org_uuid', $processedJoin->getLocalKey());
    }

    // =======================================================================
    // DEEP JOIN (NESTED) TESTS
    // =======================================================================

    public function testDeepJoinUsesConventionAtAllLevels(): void
    {
        // Level 2: Organisation (has self-referencing 'parent')
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
            // No joins needed - parent is detected via reflection
        );

        // Level 1: Club (has 'organisation' relation)
        $clubFactory = $this->createMockFactory(TestClubModel::class);
        $clubConfig = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClubModel::class,
            joins: [
                'organisation' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfig,
                    // No localKey - uses convention
                    alias: 'deep_org',
                ),
            ],
        );

        // Level 0: Main config with depth 2 (Club + Organisation)
        $mainConfig = new DbalMappingConfiguration(
            table: 'club_meeting',
            joinDepth: 2,
            joins: [
                'club' => new JoinDefinition(
                    factory: $clubFactory,
                    joinConfig: $clubConfig,
                    // No localKey - uses convention
                    alias: 'deep_club',
                ),
            ],
        );

        // Test JOIN clauses
        $joinClauses = $mainConfig->buildJoinClauses();

        // Level 1: club_club joined on club_uuid (convention)
        $this->assertStringContainsString(
            'INNER JOIN club_club AS deep_club ON club_meeting.club_uuid = deep_club.uuid',
            $joinClauses
        );

        // Level 2: contact_organisation joined on organisation_uuid (convention)
        $this->assertStringContainsString(
            'INNER JOIN contact_organisation AS deep_club_deep_org ON deep_club.organisation_uuid = deep_club_deep_org.uuid',
            $joinClauses
        );

        // Test SELECT fields include nested relation FK columns
        $selectFields = $mainConfig->buildJoinSelectFields();

        // Club columns should include organisation_uuid (detected as relation via reflection)
        $this->assertStringContainsString('deep_club.organisation_uuid AS deep_club_organisation_uuid', $selectFields);

        // Organisation columns should include parent_uuid (detected as relation via reflection)
        $this->assertStringContainsString('deep_club_deep_org.parent_uuid AS deep_club_deep_org_parent_uuid', $selectFields);
    }

    public function testDeepJoinWithMixedExplicitAndConventionKeys(): void
    {
        // Organisation with explicit localKey for parent
        $orgFactory = $this->createMockFactory(TestOrg::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrg::class,
        );

        // Club uses convention for organisation
        $clubFactory = $this->createMockFactory(TestClubModel::class);
        $clubConfig = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClubModel::class,
            joins: [
                'organisation' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfig,
                    // Convention: organisation_uuid
                    alias: 'mixed_org',
                ),
            ],
        );

        // Main config uses explicit localKey for club
        $mainConfig = new DbalMappingConfiguration(
            table: 'club_meeting',
            joinDepth: 2,
            joins: [
                'club' => new JoinDefinition(
                    factory: $clubFactory,
                    joinConfig: $clubConfig,
                    localKey: 'custom_club_fk', // Explicit override
                    alias: 'mixed_club',
                ),
            ],
        );

        $joinClauses = $mainConfig->buildJoinClauses();

        // Level 1: explicit localKey used
        $this->assertStringContainsString(
            'ON club_meeting.custom_club_fk = mixed_club.uuid',
            $joinClauses
        );

        // Level 2: convention used (organisation_uuid)
        $this->assertStringContainsString(
            'ON mixed_club.organisation_uuid = mixed_club_mixed_org.uuid',
            $joinClauses
        );
    }
}
