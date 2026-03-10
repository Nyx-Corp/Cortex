<?php

declare(strict_types=1);

// ============================================================================
// Test controller stubs with proper namespace patterns for matchesResource()
// ============================================================================

namespace CortexTest\Application\Catalog\Controller\Action {
    use Cortex\Bridge\Symfony\Controller\ControllerInterface;

    class ProductListAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }

    class ProductCreateAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }

    class ProductUpdateAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }

    class ProductEditAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }

    class ProductArchiveAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }

    class ProductSyncAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }

    class ShootingArticlesListAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }
}

namespace CortexTest\Application\Admin\Controller\Action {
    use Cortex\Bridge\Symfony\Controller\ControllerInterface;

    class AccountListAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }
}

namespace CortexTest\Application\Catalog\Controller\Action\Api {
    use Cortex\Bridge\Symfony\Controller\ControllerInterface;

    class ProductListAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }
}

namespace CortexTest\Application\Catalog\Controller\Action\WithRoute {
    use Cortex\Bridge\Symfony\Controller\ControllerInterface;
    use Symfony\Component\Routing\Attribute\Route;

    #[Route('/custom/path', name: 'custom-route', methods: ['POST'])]
    class ExplicitRouteAction implements ControllerInterface
    {
        public function __invoke(): void
        {
        }
    }
}

// ============================================================================
// Actual test class
// ============================================================================

namespace Cortex\Tests\Unit\Bridge\Symfony\Routing {
    use Cortex\Bridge\Symfony\Routing\ControllerRouteLoader;
    use CortexTest\Application\Admin\Controller\Action\AccountListAction;
    use CortexTest\Application\Catalog\Controller\Action\Api\ProductListAction as ApiProductListAction;
    use CortexTest\Application\Catalog\Controller\Action\ProductArchiveAction;
    use CortexTest\Application\Catalog\Controller\Action\ProductCreateAction;
    use CortexTest\Application\Catalog\Controller\Action\ProductEditAction;
    use CortexTest\Application\Catalog\Controller\Action\ProductListAction;
    use CortexTest\Application\Catalog\Controller\Action\ProductSyncAction;
    use CortexTest\Application\Catalog\Controller\Action\ProductUpdateAction;
    use CortexTest\Application\Catalog\Controller\Action\ShootingArticlesListAction;
    use CortexTest\Application\Catalog\Controller\Action\WithRoute\ExplicitRouteAction;
    use PHPUnit\Framework\TestCase;

    /**
     * @covers \Cortex\Bridge\Symfony\Routing\ControllerRouteLoader
     */
    class ControllerRouteLoaderTest extends TestCase
    {
        // =======================================================================
        // SUPPORTS TESTS
        // =======================================================================

        public function testSupportsCortexType(): void
        {
            $loader = new ControllerRouteLoader();

            $this->assertTrue($loader->supports('admin', 'cortex'));
            $this->assertFalse($loader->supports('admin', 'yaml'));
            $this->assertFalse($loader->supports('admin', null));
        }

        // =======================================================================
        // CONVENTION DERIVATION TESTS
        // =======================================================================

        public function testListActionDerivesCorrectRoute(): void
        {
            $loader = new ControllerRouteLoader([ProductListAction::class]);
            $routes = $loader->load('catalog', 'cortex');

            $route = $routes->get('product/index');
            $this->assertNotNull($route, 'Route product/index should exist');
            $this->assertSame('/products', $route->getPath());
            $this->assertSame(['GET'], $route->getMethods());
        }

        public function testCreateActionDerivesCorrectRoute(): void
        {
            $loader = new ControllerRouteLoader([ProductCreateAction::class]);
            $routes = $loader->load('catalog', 'cortex');

            $route = $routes->get('product/create');
            $this->assertNotNull($route, 'Route product/create should exist');
            $this->assertSame('/product/create', $route->getPath());
            $this->assertSame(['GET', 'POST'], $route->getMethods());
        }

        public function testUpdateActionDerivesCorrectRoute(): void
        {
            $loader = new ControllerRouteLoader([ProductUpdateAction::class]);
            $routes = $loader->load('catalog', 'cortex');

            $route = $routes->get('product/edit');
            $this->assertNotNull($route, 'Route product/edit should exist');
            $this->assertSame('/product/{uuid}/edit', $route->getPath());
            $this->assertSame(['GET', 'POST'], $route->getMethods());
        }

        public function testEditActionDerivesCorrectRoute(): void
        {
            $loader = new ControllerRouteLoader([ProductEditAction::class]);
            $routes = $loader->load('catalog', 'cortex');

            $route = $routes->get('product/edit');
            $this->assertNotNull($route, 'Route product/edit should exist');
            $this->assertSame('/product/{uuid}/edit', $route->getPath());
        }

        public function testArchiveActionDerivesCorrectRoute(): void
        {
            $loader = new ControllerRouteLoader([ProductArchiveAction::class]);
            $routes = $loader->load('catalog', 'cortex');

            $route = $routes->get('product/archive');
            $this->assertNotNull($route, 'Route product/archive should exist');
            $this->assertSame('/product/{uuid}/archive', $route->getPath());
            $this->assertSame(['GET'], $route->getMethods());
        }

        public function testCustomActionDerivesCorrectRoute(): void
        {
            $loader = new ControllerRouteLoader([ProductSyncAction::class]);
            $routes = $loader->load('catalog', 'cortex');

            $route = $routes->get('product/sync');
            $this->assertNotNull($route, 'Route product/sync should exist');
            $this->assertSame('/product/{uuid}/sync', $route->getPath());
            $this->assertSame(['GET', 'POST'], $route->getMethods());
        }

        // =======================================================================
        // MULTI-WORD MODEL TESTS
        // =======================================================================

        public function testMultiWordModelName(): void
        {
            $loader = new ControllerRouteLoader([ShootingArticlesListAction::class]);
            $routes = $loader->load('catalog', 'cortex');

            // "ShootingArticles" + "List" → /shooting-articless (kebab + pluralized)
            $route = $routes->get('shooting-articles/index');
            $this->assertNotNull($route, 'Route shooting-articles/index should exist');
        }

        // =======================================================================
        // RESOURCE MATCHING TESTS
        // =======================================================================

        public function testOnlyLoadsControllersMatchingResource(): void
        {
            $loader = new ControllerRouteLoader([
                ProductListAction::class,   // Application\Catalog\...
                AccountListAction::class,   // Application\Admin\...
            ]);

            $routes = $loader->load('catalog', 'cortex');

            $this->assertNotNull($routes->get('product/index'));
            $this->assertNull($routes->get('account/index'));
        }

        public function testSubpathResourceMatching(): void
        {
            $loader = new ControllerRouteLoader([ApiProductListAction::class]);
            $routes = $loader->load('api', 'cortex');

            // Controller\Action\Api\ subpath → prefix 'api/'
            $route = $routes->get('api/product/index');
            $this->assertNotNull($route, 'Route api/product/index should exist');
        }

        // =======================================================================
        // EXPLICIT ROUTE ATTRIBUTE TESTS
        // =======================================================================

        public function testExplicitRouteAttributeOverridesConvention(): void
        {
            $loader = new ControllerRouteLoader([ExplicitRouteAction::class]);
            $routes = $loader->load('catalog', 'cortex');

            // The route is in WithRoute subpath, but matched via Controller\Action pattern
            $allRoutes = iterator_to_array($routes);
            $this->assertCount(1, $allRoutes);

            $route = reset($allRoutes);
            $this->assertSame('/custom/path', $route->getPath());
            $this->assertSame(['POST'], $route->getMethods());
        }

        // =======================================================================
        // GUARD TESTS
        // =======================================================================

        public function testThrowsOnDoubleLoadSameResource(): void
        {
            $loader = new ControllerRouteLoader([]);
            $loader->load('admin', 'cortex');

            $this->expectException(\RuntimeException::class);
            $loader->load('admin', 'cortex');
        }

        public function testAllowsLoadingDifferentResources(): void
        {
            $loader = new ControllerRouteLoader([
                ProductListAction::class,
                AccountListAction::class,
            ]);

            $routes1 = $loader->load('catalog', 'cortex');
            $routes2 = $loader->load('admin', 'cortex');

            $this->assertNotNull($routes1->get('product/index'));
            $this->assertNotNull($routes2->get('account/index'));
        }

        // =======================================================================
        // MULTIPLE CONTROLLERS TESTS
        // =======================================================================

        public function testLoadsMultipleControllersForSameResource(): void
        {
            $loader = new ControllerRouteLoader([
                ProductListAction::class,
                ProductCreateAction::class,
                ProductArchiveAction::class,
            ]);

            $routes = $loader->load('catalog', 'cortex');

            $this->assertCount(3, $routes);
        }

        // =======================================================================
        // CONTROLLER DEFAULT TESTS
        // =======================================================================

        public function testControllerClassIsSetAsDefault(): void
        {
            $loader = new ControllerRouteLoader([ProductListAction::class]);
            $routes = $loader->load('catalog', 'cortex');
            $route = $routes->get('product/index');

            $this->assertSame(ProductListAction::class, $route->getDefault('_controller'));
        }
    }
}
