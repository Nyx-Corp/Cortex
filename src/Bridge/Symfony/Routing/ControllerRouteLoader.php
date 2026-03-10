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
 * Routes are derived by convention from class names, or from #[Route] attributes as override.
 *
 * Convention:
 *   {Model}ListAction    → GET /{models}                       (name: {model}/index)
 *   {Model}CreateAction  → GET|POST /{model}/create            (name: {model}/create)
 *   {Model}UpdateAction  → GET|POST /{model}/{uuid}/edit       (name: {model}/edit)
 *   {Model}EditAction    → GET|POST /{model}/{uuid}/edit       (name: {model}/edit)
 *   {Model}ArchiveAction → GET /{model}/{uuid}/archive         (name: {model}/archive)
 *   {Model}{Custom}Action → GET|POST /{model}/{uuid}/{custom}  (name: {model}/{custom})
 *
 * Usage in routes.yaml:
 *     admin:
 *         resource: 'admin'
 *         type: cortex
 */
class ControllerRouteLoader extends Loader
{
    private array $loadedResources = [];

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
        if (in_array($resource, $this->loadedResources, true)) {
            throw new \RuntimeException(sprintf('Cortex routes for resource "%s" already loaded.', $resource));
        }

        $this->loadedResources[] = $resource;
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
        // 1. If #[Route] attribute on class → use it (explicit override)
        // IS_INSTANCEOF also catches legacy Annotation\Route (extends Attribute\Route)
        $classAttributes = $reflection->getAttributes(RouteAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
        if (!empty($classAttributes)) {
            foreach ($classAttributes as $attribute) {
                $this->addRoute($collection, $reflection, $attribute->newInstance());
            }

            return;
        }

        // 2. If #[Route] attribute on __invoke → use it (explicit override)
        if ($reflection->hasMethod('__invoke')) {
            $invokeAttrs = $reflection->getMethod('__invoke')->getAttributes(RouteAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($invokeAttrs)) {
                foreach ($invokeAttrs as $attribute) {
                    $this->addRoute($collection, $reflection, $attribute->newInstance());
                }

                return;
            }
        }

        // 3. No explicit #[Route] → derive from convention
        $conventionRoute = $this->deriveRouteFromConvention($reflection);
        if ($conventionRoute) {
            $this->addRoute($collection, $reflection, $conventionRoute);
        }
    }

    /**
     * Derives a route from class name convention.
     *
     * Known action suffixes (List, Create, Update, Edit, Archive) are matched first
     * to support multi-word model names. Custom actions fallback to regex split.
     */
    private function deriveRouteFromConvention(\ReflectionClass $reflection): ?RouteAttribute
    {
        $className = $reflection->getShortName();
        $name = preg_replace('/Action$/', '', $className);

        $knownActions = ['List', 'Create', 'Update', 'Edit', 'Archive'];
        $model = null;
        $action = null;

        foreach ($knownActions as $knownAction) {
            if (str_ends_with($name, $knownAction) && \strlen($name) > \strlen($knownAction)) {
                $model = substr($name, 0, -\strlen($knownAction));
                $action = $knownAction;
                break;
            }
        }

        // Fallback: first PascalCase word is model, rest is action
        if (!$model && preg_match('/^([A-Z][a-z]+)([A-Z].*)$/', $name, $matches)) {
            $model = $matches[1];
            $action = $matches[2];
        }

        if (!$model || !$action) {
            return null;
        }

        $modelKebab = $this->toKebab($model);
        $actionKebab = $this->toKebab($action);

        [$path, $methods, $routeName] = match (strtolower($action)) {
            'list' => ['/'.$modelKebab.'s', ['GET'], $modelKebab.'/index'],
            'create' => ['/'.$modelKebab.'/create', ['GET', 'POST'], $modelKebab.'/create'],
            'update', 'edit' => ['/'.$modelKebab.'/{uuid}/edit', ['GET', 'POST'], $modelKebab.'/edit'],
            'archive' => ['/'.$modelKebab.'/{uuid}/archive', ['GET'], $modelKebab.'/archive'],
            default => ['/'.$modelKebab.'/{uuid}/'.$actionKebab, ['GET', 'POST'], $modelKebab.'/'.$actionKebab],
        };

        return new RouteAttribute(
            path: $path,
            name: $routeName,
            methods: $methods,
        );
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

    private function toKebab(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $input));
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'cortex' === $type;
    }
}
