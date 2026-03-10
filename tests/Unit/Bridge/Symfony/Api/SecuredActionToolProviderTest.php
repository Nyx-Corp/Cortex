<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Symfony\Api;

use Cortex\Bridge\Symfony\Mcp\ActionToolProvider;
use Cortex\Bridge\Symfony\Security\SecuredActionToolProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * @covers \Cortex\Bridge\Symfony\Security\SecuredActionToolProvider
 */
class SecuredActionToolProviderTest extends TestCase
{
    private ActionToolProvider $inner;
    private AccessMapInterface $accessMap;
    private AuthorizationCheckerInterface $authChecker;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(ActionToolProvider::class);
        $this->accessMap = $this->createMock(AccessMapInterface::class);
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
    }

    // =======================================================================
    // getTools() FILTERING TESTS
    // =======================================================================

    public function testFiltersOutToolsUserCannotAccess(): void
    {
        $metadata = [
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
            'Domain\\Studio\\Action\\ShootingCreate\\Command' => $this->meta('Studio', 'Shooting', 'Create'),
        ];

        $this->inner->method('getTools')->willReturn([
            'account_account_create' => ['name' => 'account_account_create'],
            'studio_shooting_create' => ['name' => 'studio_shooting_create'],
        ]);

        // Account requires ROLE_ADMIN, Studio requires ROLE_USER
        $this->accessMap->method('getPatterns')->willReturnCallback(function ($request) {
            $path = $request->getPathInfo();
            if (str_contains($path, '/account/')) {
                return [['ROLE_ADMIN'], null];
            }

            return [['ROLE_USER'], null];
        });

        // User only has ROLE_USER
        $this->authChecker->method('isGranted')->willReturnCallback(function ($role) {
            return $role === 'ROLE_USER';
        });

        $provider = new SecuredActionToolProvider($this->inner, $this->accessMap, $this->authChecker, $metadata);
        $tools = $provider->getTools();

        $this->assertArrayNotHasKey('account_account_create', $tools);
        $this->assertArrayHasKey('studio_shooting_create', $tools);
    }

    public function testAllowsPublicAccessTools(): void
    {
        $metadata = [
            'Domain\\Catalog\\Action\\ProductSync\\Command' => $this->meta('Catalog', 'Product', 'Sync'),
        ];

        $this->inner->method('getTools')->willReturn([
            'catalog_product_sync' => ['name' => 'catalog_product_sync'],
        ]);

        $this->accessMap->method('getPatterns')->willReturn([['PUBLIC_ACCESS'], null]);

        $provider = new SecuredActionToolProvider($this->inner, $this->accessMap, $this->authChecker, $metadata);
        $tools = $provider->getTools();

        $this->assertArrayHasKey('catalog_product_sync', $tools);
    }

    public function testDeniesToolsWithNoMatchingAccessRule(): void
    {
        $metadata = [
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
        ];

        $this->inner->method('getTools')->willReturn([
            'account_account_create' => ['name' => 'account_account_create'],
        ]);

        // No rules match
        $this->accessMap->method('getPatterns')->willReturn([null, null]);

        $provider = new SecuredActionToolProvider($this->inner, $this->accessMap, $this->authChecker, $metadata);
        $tools = $provider->getTools();

        $this->assertEmpty($tools);
    }

    public function testDeniesToolsWithEmptyRoles(): void
    {
        $metadata = [
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
        ];

        $this->inner->method('getTools')->willReturn([
            'account_account_create' => ['name' => 'account_account_create'],
        ]);

        $this->accessMap->method('getPatterns')->willReturn([[], null]);

        $provider = new SecuredActionToolProvider($this->inner, $this->accessMap, $this->authChecker, $metadata);
        $tools = $provider->getTools();

        $this->assertEmpty($tools);
    }

    // =======================================================================
    // handleToolCall() TESTS
    // =======================================================================

    public function testHandleToolCallDelegatesToInnerWhenAccessible(): void
    {
        $metadata = [
            'Domain\\Studio\\Action\\ShootingCreate\\Command' => $this->meta('Studio', 'Shooting', 'Create'),
        ];

        $this->inner->method('handleToolCall')
            ->with('studio_shooting_create', ['name' => 'Test'])
            ->willReturn(['uuid' => 'new-123']);

        $this->accessMap->method('getPatterns')->willReturn([['ROLE_USER'], null]);
        $this->authChecker->method('isGranted')->willReturn(true);

        $provider = new SecuredActionToolProvider($this->inner, $this->accessMap, $this->authChecker, $metadata);
        $result = $provider->handleToolCall('studio_shooting_create', ['name' => 'Test']);

        $this->assertSame(['uuid' => 'new-123'], $result);
    }

    public function testHandleToolCallDeniesAccessWhenNotAuthorized(): void
    {
        $metadata = [
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
        ];

        $this->inner->expects($this->never())->method('handleToolCall');

        $this->accessMap->method('getPatterns')->willReturn([['ROLE_ADMIN'], null]);
        $this->authChecker->method('isGranted')->willReturn(false);

        $provider = new SecuredActionToolProvider($this->inner, $this->accessMap, $this->authChecker, $metadata);
        $result = $provider->handleToolCall('account_account_create', ['name' => 'Test']);

        $this->assertSame(['error' => 'Access denied.'], $result);
    }

    public function testHandleToolCallDeniesUnknownTool(): void
    {
        $provider = new SecuredActionToolProvider($this->inner, $this->accessMap, $this->authChecker, []);
        $result = $provider->handleToolCall('unknown_tool', []);

        $this->assertSame(['error' => 'Access denied.'], $result);
    }

    // =======================================================================
    // SYNTHETIC PATH TESTS
    // =======================================================================

    public function testSyntheticPathUsesVersionedRestConvention(): void
    {
        $metadata = [
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
            'Domain\\Account\\Action\\AccountUpdate\\Command' => $this->meta('Account', 'Account', 'Update'),
            'Domain\\Account\\Action\\AccountArchive\\Command' => $this->meta('Account', 'Account', 'Archive'),
            'Domain\\Catalog\\Action\\ProductSync\\Command' => $this->meta('Catalog', 'Product', 'Sync'),
        ];

        $paths = [];
        $this->accessMap->method('getPatterns')->willReturnCallback(function ($request) use (&$paths) {
            $paths[] = $request->getPathInfo();

            return [['PUBLIC_ACCESS'], null];
        });

        $this->inner->method('getTools')->willReturn([
            'account_account_create' => ['name' => 'account_account_create'],
            'account_account_update' => ['name' => 'account_account_update'],
            'account_account_archive' => ['name' => 'account_account_archive'],
            'catalog_product_sync' => ['name' => 'catalog_product_sync'],
        ]);

        $provider = new SecuredActionToolProvider($this->inner, $this->accessMap, $this->authChecker, $metadata);
        $provider->getTools();

        $this->assertContains('/api/v1/account/account', $paths);         // create → POST /model
        $this->assertContains('/api/v1/account/account/uuid', $paths);    // update → PUT /model/{uuid}
        $this->assertContains('/api/v1/catalog/product/uuid/sync', $paths); // custom → POST /model/{uuid}/action
    }

    // =======================================================================
    // HELPERS
    // =======================================================================

    private function meta(string $domain, string $model, string $action): array
    {
        return [
            'domain' => $domain,
            'model' => $model,
            'action' => $action,
            'formType' => 'App\\Form\\'.$model.$action.'Type',
            'apiSince' => 1,
            'apiDeprecated' => null,
            'apiSunset' => null,
        ];
    }
}
