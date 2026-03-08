<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Symfony\Api;

use Cortex\Bridge\Symfony\Api\ApiController;
use Cortex\Bridge\Symfony\Api\ApiRouteLoader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\Bridge\Symfony\Api\ApiRouteLoader
 */
class ApiRouteLoaderTest extends TestCase
{
    // =======================================================================
    // ROUTE GENERATION TESTS
    // =======================================================================

    public function testCreatesPostRouteForCreateAction(): void
    {
        $loader = $this->createLoader([
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
        ]);

        $routes = $loader->load('.', 'cortex_api');
        $route = $routes->get('cortex_api_v1_account_account_create');

        $this->assertNotNull($route);
        $this->assertSame('/api/v1/account/account', $route->getPath());
        $this->assertSame(['POST'], $route->getMethods());
    }

    public function testCreatesPutPatchRouteForUpdateAction(): void
    {
        $loader = $this->createLoader([
            'Domain\\Studio\\Action\\MannequinUpdate\\Command' => $this->meta('Studio', 'Mannequin', 'Update'),
        ]);

        $routes = $loader->load('.', 'cortex_api');
        $route = $routes->get('cortex_api_v1_studio_mannequin_update');

        $this->assertNotNull($route);
        $this->assertSame('/api/v1/studio/mannequin/{uuid}', $route->getPath());
        $this->assertSame(['PUT', 'PATCH'], $route->getMethods());
    }

    public function testCreatesDeleteRouteForArchiveAction(): void
    {
        $loader = $this->createLoader([
            'Domain\\Studio\\Action\\ShootingArchive\\Command' => $this->meta('Studio', 'Shooting', 'Archive'),
        ]);

        $routes = $loader->load('.', 'cortex_api');
        $route = $routes->get('cortex_api_v1_studio_shooting_archive');

        $this->assertNotNull($route);
        $this->assertSame('/api/v1/studio/shooting/{uuid}', $route->getPath());
        $this->assertSame(['DELETE'], $route->getMethods());
    }

    public function testCreatesPostRouteForCustomAction(): void
    {
        $loader = $this->createLoader([
            'Domain\\Catalog\\Action\\ProductSync\\Command' => $this->meta('Catalog', 'Product', 'Sync'),
        ]);

        $routes = $loader->load('.', 'cortex_api');
        $route = $routes->get('cortex_api_v1_catalog_product_sync');

        $this->assertNotNull($route);
        $this->assertSame('/api/v1/catalog/product/{uuid}/sync', $route->getPath());
        $this->assertSame(['POST'], $route->getMethods());
    }

    // =======================================================================
    // ROUTE DEFAULTS TESTS
    // =======================================================================

    public function testRouteDefaultsContainControllerAndMeta(): void
    {
        $meta = $this->meta('Account', 'Account', 'Create');
        $loader = $this->createLoader([
            'Domain\\Account\\Action\\AccountCreate\\Command' => $meta,
        ]);

        $routes = $loader->load('.', 'cortex_api');
        $route = $routes->get('cortex_api_v1_account_account_create');
        $defaults = $route->getDefaults();

        $this->assertSame(ApiController::class, $defaults['_controller']);
        $this->assertSame('Domain\\Account\\Action\\AccountCreate\\Command', $defaults['_cortex_command']);
        $this->assertSame('App\\Form\\AccountCreateType', $defaults['_cortex_form_type']);
        $this->assertSame(1, $defaults['_cortex_api_version']);
    }

    public function testDeprecatedRouteHasDeprecationDefaults(): void
    {
        $meta = $this->meta('Account', 'Account', 'Update', apiSince: 1, apiDeprecated: 1, apiSunset: '2026-09-01');
        $loader = $this->createLoader([
            'Domain\\Account\\Action\\AccountUpdate\\Command' => $meta,
        ]);

        $routes = $loader->load('.', 'cortex_api');
        $route = $routes->get('cortex_api_v1_account_account_update');
        $defaults = $route->getDefaults();

        $this->assertTrue($defaults['_cortex_deprecated']);
        $this->assertSame('2026-09-01', $defaults['_cortex_sunset']);
    }

    public function testNonDeprecatedRouteHasNoDeprecationDefaults(): void
    {
        $meta = $this->meta('Account', 'Account', 'Create');
        $loader = $this->createLoader([
            'Domain\\Account\\Action\\AccountCreate\\Command' => $meta,
        ]);

        $routes = $loader->load('.', 'cortex_api');
        $route = $routes->get('cortex_api_v1_account_account_create');
        $defaults = $route->getDefaults();

        $this->assertArrayNotHasKey('_cortex_deprecated', $defaults);
        $this->assertArrayNotHasKey('_cortex_sunset', $defaults);
    }

    // =======================================================================
    // VERSIONING TESTS
    // =======================================================================

    public function testGeneratesRoutesForMultipleVersions(): void
    {
        $meta = $this->meta('Account', 'Account', 'Create', apiSince: 1);
        $loader = $this->createLoader(
            ['Domain\\Account\\Action\\AccountCreate\\Command' => $meta],
            [1, 2]
        );

        $routes = $loader->load('.', 'cortex_api');

        $this->assertNotNull($routes->get('cortex_api_v1_account_account_create'));
        $this->assertNotNull($routes->get('cortex_api_v2_account_account_create'));

        $this->assertSame('/api/v1/account/account', $routes->get('cortex_api_v1_account_account_create')->getPath());
        $this->assertSame('/api/v2/account/account', $routes->get('cortex_api_v2_account_account_create')->getPath());
    }

    public function testSkipsActionsNotAvailableInVersion(): void
    {
        $meta = $this->meta('Account', 'Account', 'Create', apiSince: 2);
        $loader = $this->createLoader(
            ['Domain\\Account\\Action\\AccountCreate\\Command' => $meta],
            [1, 2, 3]
        );

        $routes = $loader->load('.', 'cortex_api');

        $this->assertNull($routes->get('cortex_api_v1_account_account_create'));
        $this->assertNotNull($routes->get('cortex_api_v2_account_account_create'));
        $this->assertNotNull($routes->get('cortex_api_v3_account_account_create'));
    }

    public function testDeprecationOnlyAppliesFromDeprecatedVersion(): void
    {
        $meta = $this->meta('Account', 'Account', 'Update', apiSince: 1, apiDeprecated: 3, apiSunset: '2027-01-01');
        $loader = $this->createLoader(
            ['Domain\\Account\\Action\\AccountUpdate\\Command' => $meta],
            [1, 2, 3]
        );

        $routes = $loader->load('.', 'cortex_api');

        // v1 and v2: not deprecated
        $this->assertArrayNotHasKey('_cortex_deprecated', $routes->get('cortex_api_v1_account_account_update')->getDefaults());
        $this->assertArrayNotHasKey('_cortex_deprecated', $routes->get('cortex_api_v2_account_account_update')->getDefaults());

        // v3: deprecated
        $this->assertTrue($routes->get('cortex_api_v3_account_account_update')->getDefault('_cortex_deprecated'));
        $this->assertSame('2027-01-01', $routes->get('cortex_api_v3_account_account_update')->getDefault('_cortex_sunset'));
    }

    public function testVersionInRouteDefaults(): void
    {
        $loader = $this->createLoader(
            ['Domain\\A\\Action\\FooCreate\\Command' => $this->meta('A', 'Foo', 'Create')],
            [1, 2]
        );

        $routes = $loader->load('.', 'cortex_api');

        $this->assertSame(1, $routes->get('cortex_api_v1_a_foo_create')->getDefault('_cortex_api_version'));
        $this->assertSame(2, $routes->get('cortex_api_v2_a_foo_create')->getDefault('_cortex_api_version'));
    }

    // =======================================================================
    // SUPPORTS & GUARD TESTS
    // =======================================================================

    public function testSupportsCortexApiType(): void
    {
        $loader = $this->createLoader([]);

        $this->assertTrue($loader->supports('.', 'cortex_api'));
        $this->assertFalse($loader->supports('.', 'cortex'));
        $this->assertFalse($loader->supports('.', null));
    }

    public function testThrowsOnDoubleLoad(): void
    {
        $loader = $this->createLoader([]);
        $loader->load('.', 'cortex_api');

        $this->expectException(\RuntimeException::class);
        $loader->load('.', 'cortex_api');
    }

    // =======================================================================
    // MULTIPLE ACTIONS TESTS
    // =======================================================================

    public function testMultipleActionsGenerateMultipleRoutes(): void
    {
        $loader = $this->createLoader([
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
            'Domain\\Account\\Action\\AccountArchive\\Command' => $this->meta('Account', 'Account', 'Archive'),
            'Domain\\Studio\\Action\\ShootingCreate\\Command' => $this->meta('Studio', 'Shooting', 'Create'),
        ]);

        $routes = $loader->load('.', 'cortex_api');

        $this->assertCount(3, $routes);
    }

    // =======================================================================
    // HELPERS
    // =======================================================================

    private function createLoader(array $metadata, array $activeVersions = [1]): ApiRouteLoader
    {
        return new ApiRouteLoader($metadata, $activeVersions);
    }

    private function meta(
        string $domain,
        string $model,
        string $action,
        int $apiSince = 1,
        ?int $apiDeprecated = null,
        ?string $apiSunset = null,
    ): array {
        return [
            'domain' => $domain,
            'model' => $model,
            'action' => $action,
            'formType' => 'App\\Form\\'.$model.$action.'Type',
            'apiSince' => $apiSince,
            'apiDeprecated' => $apiDeprecated,
            'apiSunset' => $apiSunset,
        ];
    }
}
