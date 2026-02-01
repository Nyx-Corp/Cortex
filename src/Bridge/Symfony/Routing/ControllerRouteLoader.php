<?php

namespace Cortex\Bridge\Symfony\Routing;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Auto-discovers routes from all controllers implementing ControllerInterface.
 *
 * Controllers are injected by ControllerTaggingPass compiler pass.
 * Route names are auto-generated from namespace + class name:
 *   - Application\Contact\Controller\Action\Admin\ContactListAction → admin/contact/index
 *   - Application\Admin\Controller\Action\DashboardIndexAction → dashboard/index
 *
 * Usage in routes.yaml:
 *     admin:
 *         resource: 'admin'
 *         type: cortex
 */
class ControllerRouteLoader extends Loader
{
    private bool $loaded = false;

    /**
     * @param array<class-string<ControllerInterface>> $controllers
     */
    public function __construct(
        private readonly array $controllers = [],
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('Cortex routes already loaded.');
        }

        $this->loaded = true;
        $collection = new RouteCollection();

        foreach ($this->controllers as $controllerClass) {
            if (!$this->matchesResource($controllerClass, $resource)) {
                continue;
            }

            $reflection = new \ReflectionClass($controllerClass);
            $this->addRoutesFromController($collection, $reflection);
        }

        return $collection;
    }

    /**
     * Check if controller matches the resource filter.
     * Resource can be: 'admin', 'api', 'front', etc.
     * Matches against namespace patterns:
     *   - Application\{Resource}\Controller\Action (e.g., Application\Admin\Controller\Action)
     *   - Controller\Action\{Resource} (e.g., Application\Contact\Controller\Action\Admin).
     */
    private function matchesResource(string $controllerClass, string $resource): bool
    {
        $patterns = [
            sprintf('/Application\\\\%s\\\\Controller\\\\Action/i', preg_quote($resource, '/')),
            sprintf('/Controller\\\\Action\\\\%s\\\\/i', preg_quote($resource, '/')),
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $controllerClass)) {
                return true;
            }
        }

        return false;
    }

    private function addRoutesFromController(RouteCollection $collection, \ReflectionClass $reflection): void
    {
        $classAttributes = $reflection->getAttributes(RouteAttribute::class);

        foreach ($classAttributes as $attribute) {
            $this->addRoute($collection, $reflection, $attribute->newInstance());
        }

        if ($reflection->hasMethod('__invoke')) {
            $invokeMethod = $reflection->getMethod('__invoke');
            foreach ($invokeMethod->getAttributes(RouteAttribute::class) as $attribute) {
                $this->addRoute($collection, $reflection, $attribute->newInstance());
            }
        }
    }

    private function addRoute(RouteCollection $collection, \ReflectionClass $reflection, RouteAttribute $routeAttr): void
    {
        $prefix = $this->getNamespacePrefix($reflection);
        $routeName = $routeAttr->name
            ? $prefix.$routeAttr->name
            : $this->generateRouteName($reflection);

        $defaults = $routeAttr->defaults;
        $defaults['_controller'] = $reflection->getName();

        $route = new Route(
            path: $routeAttr->path,
            defaults: $defaults,
            requirements: $routeAttr->requirements,
            options: $routeAttr->options,
            methods: $routeAttr->methods ?: ['GET'],
        );

        $collection->add($routeName, $route);
    }

    /**
     * Extract prefix from namespace.
     *   - Application\Contact\Controller\Action\Admin → 'admin/'
     *   - Application\Admin\Controller\Action → '' (no prefix for main module).
     */
    private function getNamespacePrefix(\ReflectionClass $reflection): string
    {
        $namespace = $reflection->getNamespaceName();

        // Pattern: Controller\Action\{SubModule} (e.g., Contact module's Admin controllers)
        if (preg_match('/Controller\\\\Action\\\\(\w+)$/', $namespace, $matches)) {
            return strtolower($matches[1]).'/';
        }

        // Pattern: Application\{Module}\Controller\Action (main module, no extra prefix)
        return '';
    }

    /**
     * Generates route name from controller namespace + class name.
     *
     * Examples:
     *   - Application\Contact\Controller\Action\Admin\ContactListAction → admin/contact/index
     *   - Application\Admin\Controller\Action\DashboardIndexAction → dashboard/index
     */
    private function generateRouteName(\ReflectionClass $reflection): string
    {
        $namespace = $reflection->getNamespaceName();
        $className = $reflection->getShortName();

        // Extract module from namespace (e.g., 'Admin' from 'Controller\Action\Admin')
        $prefix = '';
        if (preg_match('/Controller\\\\Action\\\\(\w+)$/', $namespace, $matches)) {
            $prefix = strtolower($matches[1]).'/';
        }

        // Extract model/action from class name (e.g., ContactListAction → contact/index)
        $name = preg_replace('/Action$/', '', $className);

        if (preg_match('/^([A-Z][a-z]+)([A-Z].*)$/', $name, $matches)) {
            $model = strtolower($matches[1]);
            $action = strtolower($matches[2]);

            // Normalize 'list' to 'index'
            if ('list' === $action) {
                $action = 'index';
            }

            return $prefix.$model.'/'.$action;
        }

        return $prefix.strtolower($name);
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'cortex' === $type;
    }
}
