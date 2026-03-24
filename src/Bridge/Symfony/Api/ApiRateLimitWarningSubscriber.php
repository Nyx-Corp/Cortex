<?php

namespace Cortex\Bridge\Symfony\Api;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Fallback subscriber that warns when API/MCP endpoints are served without
 * a proper rate limiter (e.g. Gandalf's RateLimitSubscriber).
 *
 * When Gandalf is installed, its RateLimitSubscriber replaces this service
 * via the `cortex.api.rate_limit_guard` tag — see GandalfBundle compiler pass.
 */
class ApiRateLimitWarningSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?string $pathPrefix = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        $apiPrefix = $this->pathPrefix ?? '/api';

        if (!str_starts_with($path, $apiPrefix) && !str_starts_with($path, '/_mcp')) {
            return;
        }

        $this->logger->warning(
            'API/MCP request served without rate limiting — install Gandalf security for production use.',
            ['path' => $path]
        );
    }
}
