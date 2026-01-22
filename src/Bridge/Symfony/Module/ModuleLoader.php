<?php

namespace Cortex\Bridge\Symfony\Module;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class ModuleLoader
{
    private array $resolvedRoutes = [];

    public ?string $current = null {
        get {
            if ($this->current) {
                return $this->current;
            }

            $request = $this->requestStack->getCurrentRequest();
            if (!$request) {
                $this->logger->warning('No current request available to determine module context.');

                return null;
            }
            if ($request !== $this->requestStack->getMainRequest()) {
                $this->logger->warning('Attempting to get module name in a sub-request, module context may be incorrect.');
            }

            $routeName = $request?->attributes->get('_route');
            if (in_array($routeName, $this->resolvedRoutes, true)) {
                return $this->current;
            }

            $route = $routeName ? $this->router->getRouteCollection()->get($routeName) : null;

            $this->current = $route?->getOption('module') ?? null;
            $this->resolvedRoutes[] = $routeName;

            return $this->current;
        }
    }

    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }
}
