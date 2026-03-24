<?php

namespace Cortex\Bridge\Symfony\Api;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ApiRouteLoader extends Loader
{
    private bool $loaded = false;

    /**
     * @param ?string $pathPrefix Custom path prefix (e.g. '/p'). When set, routes use
     *                            this prefix without version segment. When null, defaults
     *                            to '/api/v{version}'.
     */
    public function __construct(
        private readonly array $actionMetadata,
        private readonly array $activeVersions = [1],
        private readonly ?string $pathPrefix = null,
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
                $routeName = null !== $this->pathPrefix
                    ? sprintf('cortex_api_%s_%s_%s', $domain, $model, $action)
                    : sprintf('cortex_api_v%d_%s_%s_%s', $version, $domain, $model, $action);

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

                $base = $this->pathPrefix ?? sprintf('/api/v%d', $version);
                $path = match ($action) {
                    'create' => sprintf('%s/%s/%s', $base, $domain, $model),
                    'update' => sprintf('%s/%s/%s/{uuid}', $base, $domain, $model),
                    'archive' => sprintf('%s/%s/%s/{uuid}', $base, $domain, $model),
                    default => sprintf('%s/%s/%s/{uuid}/%s', $base, $domain, $model, $action),
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
