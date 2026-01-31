<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Doctrine;

use Cortex\Bridge\Doctrine\DbalAdapter;
use Cortex\Bridge\Doctrine\DbalMappingConfiguration;
use Cortex\Bridge\Doctrine\DbalPreloader;
use Cortex\Bridge\Doctrine\JoinDefinition;
use Cortex\Component\Collection\StructuredMap;
use Cortex\Component\Mapper\ArrayMapper;
use Cortex\Component\Mapper\Relation;
use Cortex\Component\Mapper\Strategy;
use Cortex\Component\Middleware\Middleware;
use Cortex\Component\Model\Factory\ModelFactory;
use Cortex\Component\Model\ModelCollection;
use Cortex\Component\Model\Query\Factory\QueryFactory;
use Cortex\Component\Model\Query\ModelQuery;
use Cortex\ValueObject\RegisteredClass;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;

// Test fixtures
class TestOrganisation
{
    public function __construct(
        public string $uuid,
        public string $name,
    ) {}
}

class TestClub
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?TestOrganisation $organisation = null,
    ) {}
}

/**
 * @covers \Cortex\Bridge\Doctrine\DbalAdapter
 * @covers \Cortex\Bridge\Doctrine\DbalMappingConfiguration
 */
class DbalAdapterTest extends TestCase
{
    private Connection $connection;
    private DbalPreloader $preloader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->preloader = $this->createMock(DbalPreloader::class);
    }

    /**
     * Create a real ModelQuery instance for testing.
     */
    private function createModelQuery(): ModelQuery
    {
        $filters = new StructuredMap();
        // Declare common filter keys that DbalAdapter expects
        $filters->declare('uuid', nullable: true);

        return new ModelQuery(
            resolver: fn() => yield from [],
            modelCollectionClass: new RegisteredClass(ModelCollection::class),
            filters: $filters,
        );
    }

    /**
     * Create a Middleware chain marked as "last" (no next middleware).
     */
    private function createLastMiddleware(): Middleware
    {
        // Create a middleware without a next, so isLast will be true
        $middleware = new Middleware(fn($chain, $cmd) => yield from []);
        // wrap() with null makes isLast = true
        $middleware->wrap(null, []);
        return $middleware;
    }

    // =======================================================================
    // CONFIGURATION TESTS
    // =======================================================================

    public function testAutoHydrateDefaultsToTrue(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
        );

        $this->assertTrue($config->autoHydrate);
    }

    public function testAutoHydrateCanBeSetToFalse(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
            autoHydrate: false,
        );

        $this->assertFalse($config->autoHydrate);
    }

    public function testAutoHydrateCanBeSetToTrue(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
            autoHydrate: true,
        );

        $this->assertTrue($config->autoHydrate);
    }

    // =======================================================================
    // ONMODELQUERY INTEGRATION TESTS
    // =======================================================================

    public function testOnModelQueryWithAutoHydrateEnabled(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
            tableToModelMapper: new ArrayMapper(format: Strategy::AutoMapCamel),
            autoHydrate: true,
        );

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        $mockResult = $this->createMock(Result::class);
        $mockResult->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['uuid' => 'test-uuid', 'name' => 'Test Name'],
                false
            );

        $this->connection->method('executeQuery')->willReturn($mockResult);
        $this->preloader->method('has')->willReturn(false);

        $modelQuery = $this->createModelQuery()->limit(0)->paginate(null);

        $chain = $this->createLastMiddleware();

        $results = iterator_to_array($adapter->onModelQuery($chain, $modelQuery));

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('test-uuid', $results);
        $this->assertEquals('Test Name', $results['test-uuid']['_default']['name']);
    }

    public function testOnModelQueryWithAutoHydrateDisabled(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
            tableToModelMapper: new ArrayMapper(format: Strategy::AutoMapCamel),
            autoHydrate: false,
        );

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        $mockResult = $this->createMock(Result::class);
        $mockResult->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['uuid' => 'test-uuid', 'name' => 'Test Name'],
                false
            );

        $this->connection->method('executeQuery')->willReturn($mockResult);
        $this->preloader->method('has')->willReturn(false);

        $modelQuery = $this->createModelQuery()->limit(0)->paginate(null);

        $chain = $this->createLastMiddleware();

        $results = iterator_to_array($adapter->onModelQuery($chain, $modelQuery));

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('test-uuid', $results);
        $this->assertEquals('Test Name', $results['test-uuid']['_default']['name']);
    }

    // =======================================================================
    // HYDRATE RELATIONS UNIT TESTS (via reflection)
    // =======================================================================

    public function testHydrateRelationsWithEmptyJoinsReturnsUnchanged(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
            joins: [], // Empty joins - nothing to hydrate
            autoHydrate: true,
        );

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        $reflection = new \ReflectionClass($adapter);
        $method = $reflection->getMethod('hydrateRelations');

        $mappedData = [
            'uuid' => 'test-uuid',
            'name' => 'Test Name',
            'organisation' => 'org-uuid-123', // String UUID, but no join for it
        ];
        $result = $method->invoke($adapter, $mappedData);

        // With empty joins, nothing should be hydrated
        $this->assertEquals('test-uuid', $result['uuid']);
        $this->assertEquals('Test Name', $result['name']);
        $this->assertEquals('org-uuid-123', $result['organisation']); // Still a string
    }

    public function testHydrateRelationsPreservesNullValues(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
            joins: [],
            autoHydrate: true,
        );

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        $reflection = new \ReflectionClass($adapter);
        $method = $reflection->getMethod('hydrateRelations');

        $mappedData = ['organisation' => null, 'name' => 'Test'];
        $result = $method->invoke($adapter, $mappedData);

        $this->assertNull($result['organisation']);
        $this->assertEquals('Test', $result['name']);
    }

    public function testHydrateRelationsPreservesObjectValues(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
            joins: [],
            autoHydrate: true,
        );

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        $reflection = new \ReflectionClass($adapter);
        $method = $reflection->getMethod('hydrateRelations');

        $existingOrg = new TestOrganisation('uuid', 'name');
        $mappedData = ['organisation' => $existingOrg, 'name' => 'Test'];
        $result = $method->invoke($adapter, $mappedData);

        $this->assertSame($existingOrg, $result['organisation']);
    }

    public function testHydrateRelationsPreservesAllFields(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
            joins: [],
            autoHydrate: true,
        );

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        $reflection = new \ReflectionClass($adapter);
        $method = $reflection->getMethod('hydrateRelations');

        $mappedData = [
            'uuid' => 'test-uuid',
            'name' => 'Test Name',
            'extra' => 'Extra Value',
            'number' => 42,
            'bool' => true,
        ];
        $result = $method->invoke($adapter, $mappedData);

        $this->assertEquals($mappedData, $result);
    }

    // =======================================================================
    // FULL INTEGRATION TEST WITH REAL JOINDEFINITION
    // =======================================================================

    public function testAutoHydrateWithRealJoinDefinition(): void
    {
        // Create a mock ModelQuery that will be returned by the organisation factory
        $mockOrganisation = new TestOrganisation('org-uuid-123', 'Test Org');

        $mockOrgModelQuery = $this->createMock(ModelQuery::class);
        $mockOrgModelQuery->method('filterBy')->willReturnSelf();
        $mockOrgModelQuery->method('first')->willReturn($mockOrganisation);

        $queryFactory = $this->createMock(QueryFactory::class);
        $queryFactory->method('createQuery')->willReturn($mockOrgModelQuery);

        $orgFactory = new ModelFactory(
            TestOrganisation::class,
            $queryFactory,
        );

        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        // Create JoinDefinition with explicit alias to avoid static counter issues
        $join = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            localKey: 'organisation_uuid',
            alias: 'org', // Explicit alias
        );

        // Create the main configuration with the join
        $config = new DbalMappingConfiguration(
            table: 'club_club',
            joins: ['organisation' => $join],
            tableToModelMapper: new ArrayMapper(
                mapping: [
                    'organisation_uuid' => Relation::toModel('organisation'),
                ],
                format: Strategy::AutoMapCamel
            ),
            autoHydrate: true,
        );

        // Setup preloader - set() should be called with preloaded data
        $this->preloader->method('has')->willReturn(false);
        $this->preloader->expects($this->once())
            ->method('set')
            ->with(
                TestOrganisation::class,
                'org-uuid-123',
                $this->callback(fn($data) => $data['uuid'] === 'org-uuid-123')
            );

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        // Mock database result with joined data using the explicit alias 'org'
        $mockResult = $this->createMock(Result::class);
        $mockResult->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    'uuid' => 'club-uuid-456',
                    'name' => 'Test Club',
                    'organisation_uuid' => 'org-uuid-123',
                    // Joined columns (prefixed by alias 'org')
                    'org_uuid' => 'org-uuid-123',
                    'org_name' => 'Test Org',
                ],
                false
            );

        $this->connection->method('executeQuery')->willReturn($mockResult);

        $modelQuery = $this->createModelQuery()->limit(0)->paginate(null);

        $chain = $this->createLastMiddleware();

        // Execute
        $results = iterator_to_array($adapter->onModelQuery($chain, $modelQuery));

        // Verify results
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('club-uuid-456', $results);

        $dataLine = $results['club-uuid-456']['_default'];

        // The organisation should be hydrated to the mock object
        $this->assertInstanceOf(TestOrganisation::class, $dataLine['organisation']);
        $this->assertEquals('org-uuid-123', $dataLine['organisation']->uuid);
        $this->assertEquals('Test Org', $dataLine['organisation']->name);
    }

    public function testAutoHydrateDisabledWithRealJoinDefinition(): void
    {
        // Factory query().first() should NOT be called when autoHydrate is disabled
        $mockOrgModelQuery = $this->createMock(ModelQuery::class);
        $mockOrgModelQuery->method('filterBy')->willReturnSelf();
        $mockOrgModelQuery->expects($this->never())->method('first');

        $queryFactory = $this->createMock(QueryFactory::class);
        $queryFactory->method('createQuery')->willReturn($mockOrgModelQuery);

        $orgFactory = new ModelFactory(
            TestOrganisation::class,
            $queryFactory,
        );

        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        $join = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            localKey: 'organisation_uuid',
            alias: 'org2', // Explicit alias to avoid static counter issues
        );

        // Create configuration with autoHydrate DISABLED
        $config = new DbalMappingConfiguration(
            table: 'club_club',
            joins: ['organisation' => $join],
            tableToModelMapper: new ArrayMapper(
                mapping: [
                    'organisation_uuid' => Relation::toModel('organisation'),
                ],
                format: Strategy::AutoMapCamel
            ),
            autoHydrate: false, // DISABLED
        );

        $this->preloader->method('has')->willReturn(false);

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        $mockResult = $this->createMock(Result::class);
        $mockResult->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    'uuid' => 'club-uuid-456',
                    'name' => 'Test Club',
                    'organisation_uuid' => 'org-uuid-123',
                    'org2_uuid' => 'org-uuid-123',
                    'org2_name' => 'Test Org',
                ],
                false
            );

        $this->connection->method('executeQuery')->willReturn($mockResult);

        $modelQuery = $this->createModelQuery()->limit(0)->paginate(null);

        $chain = $this->createLastMiddleware();

        $results = iterator_to_array($adapter->onModelQuery($chain, $modelQuery));

        $this->assertCount(1, $results);
        $dataLine = $results['club-uuid-456']['_default'];

        // Organisation should remain as string UUID (not hydrated)
        $this->assertIsString($dataLine['organisation']);
        $this->assertEquals('org-uuid-123', $dataLine['organisation']);
    }

    // =======================================================================
    // PRELOADER PATH TESTS (data from cache)
    // =======================================================================

    /**
     * Test that auto-hydration works on preloaded data.
     *
     * This tests the bug fix where hydrateRelations() was not called
     * when data was retrieved from the preloader cache.
     */
    public function testAutoHydrateWorksOnPreloadedData(): void
    {
        // Create mock organisation that will be returned by the factory
        $mockOrganisation = new TestOrganisation('org-uuid-123', 'Preloaded Org');

        $mockOrgModelQuery = $this->createMock(ModelQuery::class);
        $mockOrgModelQuery->method('filterBy')->willReturnSelf();
        $mockOrgModelQuery->method('first')->willReturn($mockOrganisation);

        $queryFactory = $this->createMock(QueryFactory::class);
        $queryFactory->method('createQuery')->willReturn($mockOrgModelQuery);

        $orgFactory = new ModelFactory(
            TestOrganisation::class,
            $queryFactory,
        );

        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        $join = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            localKey: 'organisation_uuid',
            alias: 'org_preload',
        );

        $config = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClub::class,
            joins: ['organisation' => $join],
            tableToModelMapper: new ArrayMapper(
                mapping: [
                    'organisation_uuid' => Relation::toModel('organisation'),
                ],
                format: Strategy::AutoMapCamel
            ),
            autoHydrate: true,
        );

        // Preloader HAS cached data for this UUID
        $cachedData = [
            'uuid' => 'club-uuid-789',
            'name' => 'Cached Club',
            'organisation_uuid' => 'org-uuid-123',
        ];

        $this->preloader->method('has')
            ->with(TestClub::class, 'club-uuid-789')
            ->willReturn(true);

        $this->preloader->method('get')
            ->with(TestClub::class, 'club-uuid-789')
            ->willReturn($cachedData);

        $this->preloader->expects($this->once())
            ->method('remove')
            ->with(TestClub::class, 'club-uuid-789');

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        // Create query that filters by uuid (triggers preloader path)
        $modelQuery = $this->createModelQuery()
            ->filterBy('uuid', 'club-uuid-789')
            ->limit(0)
            ->paginate(null);

        $chain = $this->createLastMiddleware();

        // Execute - should NOT hit database, should use preloader
        $results = iterator_to_array($adapter->onModelQuery($chain, $modelQuery));

        // Verify results
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('club-uuid-789', $results);

        $dataLine = $results['club-uuid-789']['_default'];

        // The organisation should be hydrated even from preloaded data
        $this->assertInstanceOf(
            TestOrganisation::class,
            $dataLine['organisation'],
            'Organisation should be hydrated to object, not remain as UUID string'
        );
        $this->assertEquals('org-uuid-123', $dataLine['organisation']->uuid);
        $this->assertEquals('Preloaded Org', $dataLine['organisation']->name);
    }

    /**
     * Test that auto-hydration is NOT applied on preloaded data when disabled.
     */
    public function testAutoHydrateDisabledOnPreloadedData(): void
    {
        $mockOrgModelQuery = $this->createMock(ModelQuery::class);
        $mockOrgModelQuery->method('filterBy')->willReturnSelf();
        $mockOrgModelQuery->expects($this->never())->method('first');

        $queryFactory = $this->createMock(QueryFactory::class);
        $queryFactory->method('createQuery')->willReturn($mockOrgModelQuery);

        $orgFactory = new ModelFactory(
            TestOrganisation::class,
            $queryFactory,
        );

        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        $join = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            localKey: 'organisation_uuid',
            alias: 'org_preload_disabled',
        );

        $config = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClub::class,
            joins: ['organisation' => $join],
            tableToModelMapper: new ArrayMapper(
                mapping: [
                    'organisation_uuid' => Relation::toModel('organisation'),
                ],
                format: Strategy::AutoMapCamel
            ),
            autoHydrate: false, // DISABLED
        );

        $cachedData = [
            'uuid' => 'club-uuid-disabled',
            'name' => 'Cached Club',
            'organisation_uuid' => 'org-uuid-456',
        ];

        $this->preloader->method('has')
            ->with(TestClub::class, 'club-uuid-disabled')
            ->willReturn(true);

        $this->preloader->method('get')
            ->with(TestClub::class, 'club-uuid-disabled')
            ->willReturn($cachedData);

        $adapter = new DbalAdapter($this->connection, $config, $this->preloader);

        $modelQuery = $this->createModelQuery()
            ->filterBy('uuid', 'club-uuid-disabled')
            ->limit(0)
            ->paginate(null);

        $chain = $this->createLastMiddleware();

        $results = iterator_to_array($adapter->onModelQuery($chain, $modelQuery));

        $this->assertCount(1, $results);
        $dataLine = $results['club-uuid-disabled']['_default'];

        // Organisation should remain as string UUID (not hydrated)
        $this->assertIsString($dataLine['organisation']);
        $this->assertEquals('org-uuid-456', $dataLine['organisation']);
    }

    // =======================================================================
    // NESTED JOINS TESTS
    // =======================================================================

    public function testJoinDepthDefaultsToOne(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
        );

        $this->assertEquals(1, $config->joinDepth);
    }

    public function testJoinDepthCanBeSet(): void
    {
        $config = new DbalMappingConfiguration(
            table: 'test_table',
            joinDepth: 3,
        );

        $this->assertEquals(3, $config->joinDepth);
    }

    public function testBuildJoinClausesWithDepthOne(): void
    {
        // Create organisation factory/config (level 2 - should NOT be included with depth 1)
        $orgFactory = $this->createMockFactory(TestOrganisation::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        // Create club factory/config with join to organisation (level 1)
        $clubFactory = $this->createMockFactory(TestClub::class);
        $clubConfig = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClub::class,
            joins: [
                'organisation' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfig,
                    localKey: 'organisation_uuid',
                    alias: 'nested_org1',
                ),
            ],
        );

        // Main config with join to club, depth 1 (only club, not organisation)
        $mainConfig = new DbalMappingConfiguration(
            table: 'club_attendance',
            joinDepth: 1, // Only first level
            joins: [
                'club' => new JoinDefinition(
                    factory: $clubFactory,
                    joinConfig: $clubConfig,
                    localKey: 'club_uuid',
                    alias: 'nested_club1',
                ),
            ],
        );

        $joinClauses = $mainConfig->buildJoinClauses();

        // Should include club join
        $this->assertStringContainsString('INNER JOIN club_club AS nested_club1', $joinClauses);
        // Should NOT include nested organisation join (depth 1 = no nesting)
        $this->assertStringNotContainsString('contact_organisation', $joinClauses);
    }

    public function testBuildJoinClausesWithDepthTwo(): void
    {
        // Create organisation factory/config (level 2)
        $orgFactory = $this->createMockFactory(TestOrganisation::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        // Create club factory/config with join to organisation (level 1)
        $clubFactory = $this->createMockFactory(TestClub::class);
        $clubConfig = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClub::class,
            joins: [
                'organisation' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfig,
                    localKey: 'organisation_uuid',
                    alias: 'nested_org2',
                ),
            ],
        );

        // Main config with join to club, depth 2 (club + organisation)
        $mainConfig = new DbalMappingConfiguration(
            table: 'club_attendance',
            joinDepth: 2, // Two levels
            joins: [
                'club' => new JoinDefinition(
                    factory: $clubFactory,
                    joinConfig: $clubConfig,
                    localKey: 'club_uuid',
                    alias: 'nested_club2',
                ),
            ],
        );

        $joinClauses = $mainConfig->buildJoinClauses();

        // Should include club join
        $this->assertStringContainsString('INNER JOIN club_club AS nested_club2', $joinClauses);
        // Should include nested organisation join with hierarchical alias
        $this->assertStringContainsString('INNER JOIN contact_organisation AS nested_club2_', $joinClauses);
    }

    public function testBuildJoinSelectFieldsWithDepthTwo(): void
    {
        // Create organisation factory/config (level 2)
        $orgFactory = $this->createMockFactory(TestOrganisation::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        // Create club factory/config with join to organisation (level 1)
        $clubFactory = $this->createMockFactory(TestClub::class);
        $clubConfig = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClub::class,
            joins: [
                'organisation' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfig,
                    localKey: 'organisation_uuid',
                    alias: 'nested_org3',
                ),
            ],
        );

        // Main config with depth 2
        $mainConfig = new DbalMappingConfiguration(
            table: 'club_attendance',
            joinDepth: 2,
            joins: [
                'club' => new JoinDefinition(
                    factory: $clubFactory,
                    joinConfig: $clubConfig,
                    localKey: 'club_uuid',
                    alias: 'nested_club3',
                ),
            ],
        );

        $selectFields = $mainConfig->buildJoinSelectFields();

        // Should include club fields
        $this->assertStringContainsString('nested_club3.uuid AS nested_club3_uuid', $selectFields);
        $this->assertStringContainsString('nested_club3.name AS nested_club3_name', $selectFields);

        // Should include nested organisation fields with hierarchical alias
        // Organisation fields should have hierarchical prefix: nested_club3_nested_org3
        $this->assertStringContainsString('nested_club3_nested_org3.uuid AS nested_club3_nested_org3_uuid', $selectFields);
        $this->assertStringContainsString('nested_club3_nested_org3.name AS nested_club3_nested_org3_name', $selectFields);
    }

    public function testCircularReferencesPrevented(): void
    {
        // Create organisation config that references itself (parent)
        $orgFactory = $this->createMockFactory(TestOrganisation::class);

        // First, create a config without joins (to avoid infinite recursion in test setup)
        $orgConfigNoJoins = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        // Now create the self-referencing config
        $orgConfigWithParent = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
            joinDepth: 10, // High depth to test circular reference protection
            joins: [
                'parent' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfigNoJoins, // Points to org config (same modelClass)
                    localKey: 'parent_uuid',
                    alias: 'circular_parent',
                ),
            ],
        );

        // Should not throw and should not infinite loop
        $joinClauses = $orgConfigWithParent->buildJoinClauses();

        // Should include one parent join
        $this->assertStringContainsString('INNER JOIN contact_organisation AS circular_parent', $joinClauses);

        // Count occurrences - should only have one join (circular reference prevented)
        $count = substr_count($joinClauses, 'INNER JOIN contact_organisation');
        $this->assertEquals(1, $count, 'Circular reference should be prevented - only one join');
    }

    public function testWithParentAliasCreatesHierarchicalAlias(): void
    {
        $orgFactory = $this->createMockFactory(TestOrganisation::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        $originalJoin = new JoinDefinition(
            factory: $orgFactory,
            joinConfig: $orgConfig,
            localKey: 'organisation_uuid',
            alias: 'orig',
        );

        $this->assertEquals('orig', $originalJoin->getAlias());

        // Create copy with parent alias
        $nestedJoin = $originalJoin->withParentAlias('parent');

        // New alias should be hierarchical: parent_orig (preserves base alias)
        $this->assertEquals('parent_orig', $nestedJoin->getAlias());
    }

    public function testNestedJoinPreloadsAllLevels(): void
    {
        // Create organisation factory/config (level 2)
        $orgFactory = $this->createMockFactory(TestOrganisation::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        // Create club factory/config with join to organisation (level 1)
        $clubFactory = $this->createMockFactory(TestClub::class);
        $clubConfig = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClub::class,
            joins: [
                'organisation' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfig,
                    localKey: 'organisation_uuid',
                    alias: 'preload_org',
                ),
            ],
        );

        // Main config with depth 2
        $mainConfig = new DbalMappingConfiguration(
            table: 'club_attendance',
            joinDepth: 2,
            joins: [
                'club' => new JoinDefinition(
                    factory: $clubFactory,
                    joinConfig: $clubConfig,
                    localKey: 'club_uuid',
                    alias: 'preload_club',
                ),
            ],
            tableToModelMapper: new ArrayMapper(format: Strategy::AutoMapCamel),
        );

        // Expect preloader to be called for both Club and Organisation
        $preloaderCalls = [];
        $this->preloader->method('set')
            ->willReturnCallback(function ($class, $id, $data) use (&$preloaderCalls) {
                $preloaderCalls[] = ['class' => $class, 'id' => $id];
            });
        $this->preloader->method('has')->willReturn(false);

        $adapter = new DbalAdapter($this->connection, $mainConfig, $this->preloader);

        // Mock database result with nested joined data
        $mockResult = $this->createMock(Result::class);
        $mockResult->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    'uuid' => 'attendance-uuid',
                    'club_uuid' => 'club-uuid',
                    // Level 1 join: club
                    'preload_club_uuid' => 'club-uuid',
                    'preload_club_name' => 'Test Club',
                    'preload_club_organisation' => null,
                    // Level 2 join: organisation (hierarchical alias)
                    'preload_club_preload_org_uuid' => 'org-uuid',
                    'preload_club_preload_org_name' => 'Test Org',
                ],
                false
            );

        $this->connection->method('executeQuery')->willReturn($mockResult);

        $modelQuery = $this->createModelQuery()->limit(0)->paginate(null);
        $chain = $this->createLastMiddleware();

        iterator_to_array($adapter->onModelQuery($chain, $modelQuery));

        // Verify both levels were preloaded
        $this->assertCount(2, $preloaderCalls, 'Should preload both Club and Organisation');

        $classes = array_column($preloaderCalls, 'class');
        $this->assertContains(TestClub::class, $classes);
        $this->assertContains(TestOrganisation::class, $classes);
    }

    public function testNestedJoinStripsAllPrefixedColumns(): void
    {
        // Create organisation factory/config (level 2)
        $orgFactory = $this->createMockFactory(TestOrganisation::class);
        $orgConfig = new DbalMappingConfiguration(
            table: 'contact_organisation',
            modelClass: TestOrganisation::class,
        );

        // Create club factory/config with join to organisation (level 1)
        $clubFactory = $this->createMockFactory(TestClub::class);
        $clubConfig = new DbalMappingConfiguration(
            table: 'club_club',
            modelClass: TestClub::class,
            joins: [
                'organisation' => new JoinDefinition(
                    factory: $orgFactory,
                    joinConfig: $orgConfig,
                    localKey: 'organisation_uuid',
                    alias: 'strip_org',
                ),
            ],
        );

        // Main config with depth 2
        $mainConfig = new DbalMappingConfiguration(
            table: 'club_attendance',
            joinDepth: 2,
            joins: [
                'club' => new JoinDefinition(
                    factory: $clubFactory,
                    joinConfig: $clubConfig,
                    localKey: 'club_uuid',
                    alias: 'strip_club',
                ),
            ],
            tableToModelMapper: new ArrayMapper(format: Strategy::AutoMapCamel),
        );

        $this->preloader->method('has')->willReturn(false);

        $adapter = new DbalAdapter($this->connection, $mainConfig, $this->preloader);

        // Mock database result with nested joined data
        $mockResult = $this->createMock(Result::class);
        $mockResult->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    'uuid' => 'attendance-uuid',
                    'club_uuid' => 'club-uuid',
                    'name' => 'Attendance Name',
                    // Level 1 join columns (should be stripped)
                    'strip_club_uuid' => 'club-uuid',
                    'strip_club_name' => 'Test Club',
                    'strip_club_organisation' => null,
                    // Level 2 join columns (should also be stripped)
                    'strip_club_strip_org_uuid' => 'org-uuid',
                    'strip_club_strip_org_name' => 'Test Org',
                ],
                false
            );

        $this->connection->method('executeQuery')->willReturn($mockResult);

        $modelQuery = $this->createModelQuery()->limit(0)->paginate(null);
        $chain = $this->createLastMiddleware();

        $results = iterator_to_array($adapter->onModelQuery($chain, $modelQuery));

        $dataLine = $results['attendance-uuid']['_default'];

        // Main columns should remain
        $this->assertArrayHasKey('uuid', $dataLine);
        $this->assertArrayHasKey('name', $dataLine);
        $this->assertArrayHasKey('clubUuid', $dataLine); // camelCase from mapper

        // Joined columns should be stripped
        $this->assertArrayNotHasKey('strip_club_uuid', $dataLine);
        $this->assertArrayNotHasKey('strip_club_name', $dataLine);
        $this->assertArrayNotHasKey('strip_club_strip_org_uuid', $dataLine);
        $this->assertArrayNotHasKey('strip_club_strip_org_name', $dataLine);
    }

    // =======================================================================
    // HELPER METHODS
    // =======================================================================

    /**
     * Create a mock ModelFactory for testing.
     */
    private function createMockFactory(string $modelClass): ModelFactory
    {
        $mockModelQuery = $this->createMock(ModelQuery::class);
        $mockModelQuery->method('filterBy')->willReturnSelf();
        $mockModelQuery->method('first')->willReturn(null);

        $queryFactory = $this->createMock(QueryFactory::class);
        $queryFactory->method('createQuery')->willReturn($mockModelQuery);

        return new ModelFactory($modelClass, $queryFactory);
    }
}
