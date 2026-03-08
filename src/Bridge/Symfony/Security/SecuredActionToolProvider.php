<?php

namespace Cortex\Bridge\Symfony\Security;

use Cortex\Bridge\Symfony\Mcp\ActionToolProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * Decorator that filters MCP tools based on Symfony's access_control rules.
 *
 * Each MCP tool has a corresponding API path (/api/{domain}/{model}/{action}).
 * We create a synthetic Request for that path and resolve the required role(s)
 * via AccessMapInterface — the same rules defined in security.yaml.
 */
class SecuredActionToolProvider
{
    public function __construct(
        private readonly ActionToolProvider $inner,
        private readonly AccessMapInterface $accessMap,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly array $actionMetadata,
    ) {
    }

    /**
     * Returns only the tools the current user is allowed to access.
     */
    public function getTools(): array
    {
        $allTools = $this->inner->getTools();
        $filtered = [];

        foreach ($allTools as $name => $tool) {
            if ($this->isToolAccessible($name)) {
                $filtered[$name] = $tool;
            }
        }

        return $filtered;
    }

    /**
     * Verifies access before delegating to the inner provider.
     */
    public function handleToolCall(string $name, array $args): array
    {
        if (!$this->isToolAccessible($name)) {
            return ['error' => 'Access denied.'];
        }

        return $this->inner->handleToolCall($name, $args);
    }

    private function isToolAccessible(string $toolName): bool
    {
        $apiPath = $this->resolveApiPath($toolName);

        if (null === $apiPath) {
            return false;
        }

        $request = Request::create($apiPath, 'POST');
        [$roles] = $this->accessMap->getPatterns($request);

        if (null === $roles || [] === $roles) {
            // No access_control rule matched — deny by default
            return false;
        }

        foreach ($roles as $role) {
            if ('PUBLIC_ACCESS' === $role) {
                return true;
            }

            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    private function resolveApiPath(string $toolName): ?string
    {
        foreach ($this->actionMetadata as $meta) {
            $name = sprintf(
                '%s_%s_%s',
                strtolower($meta['domain']),
                $this->camelToSnake($meta['model']),
                $this->camelToSnake($meta['action'])
            );

            if ($name !== $toolName) {
                continue;
            }

            $domain = strtolower($meta['domain']);
            $model = strtolower($meta['model']);
            $action = strtolower($meta['action']);

            return match ($action) {
                'create' => sprintf('/api/v1/%s/%s', $domain, $model),
                'update', 'archive' => sprintf('/api/v1/%s/%s/uuid', $domain, $model),
                default => sprintf('/api/v1/%s/%s/uuid/%s', $domain, $model, $action),
            };
        }

        return null;
    }

    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
