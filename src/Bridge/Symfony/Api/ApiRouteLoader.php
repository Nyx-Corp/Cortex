<?php

namespace Cortex\Bridge\Symfony\Api;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ApiRouteLoader extends Loader
{
    private bool $loaded = false;

    public function __construct(
        private readonly array $actionMetadata,
        private readonly array $activeVersions = [1],
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('ApiRouteLoader already loaded.');
        }

        $this->loaded = true;
        $routes = new RouteCollection();

        foreach ($this->activeVersions as $version) {
            foreach ($this->actionMetadata as $commandClass => $meta) {
                if ($version < ($meta['apiSince'] ?? 1)) {
                    continue;
                }

                $domain = strtolower($meta['domain']);
                $model = strtolower($meta['model']);
                $action = strtolower($meta['action']);
                $routeName = sprintf('cortex_api_v%d_%s_%s_%s', $version, $domain, $model, $action);

                $defaults = [
                    '_controller' => ApiController::class,
                    '_cortex_command' => $commandClass,
                    '_cortex_form_type' => $meta['formType'],
                    '_cortex_meta' => $meta,
                    '_cortex_api_version' => $version,
                ];

                if (($meta['apiDeprecated'] ?? null) !== null && $version >= $meta['apiDeprecated']) {
                    $defaults['_cortex_deprecated'] = true;
                    if ($meta['apiSunset'] ?? null) {
                        $defaults['_cortex_sunset'] = $meta['apiSunset'];
                    }
                }

                $methods = match ($action) {
                    'create' => ['POST'],
                    'update' => ['PUT', 'PATCH'],
                    'archive' => ['DELETE'],
                    default => ['POST'],
                };

                $path = match ($action) {
                    'create' => sprintf('/api/v%d/%s/%s', $version, $domain, $model),
                    'update' => sprintf('/api/v%d/%s/%s/{uuid}', $version, $domain, $model),
                    'archive' => sprintf('/api/v%d/%s/%s/{uuid}', $version, $domain, $model),
                    default => sprintf('/api/v%d/%s/%s/{uuid}/%s', $version, $domain, $model, $action),
                };

                $route = new Route($path, $defaults, [], [], '', [], $methods);
                $routes->add($routeName, $route);
            }
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'cortex_api' === $type;
    }
}
