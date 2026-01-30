<?php

declare(strict_types=1);

namespace Cortex\Bridge\Doctrine;

/**
 * In-memory implementation of DbalPreloader.
 *
 * Stores preloaded data in memory for the duration of the request.
 * Data is automatically cleared after being retrieved (consume-once pattern).
 */
class InMemoryDbalPreloader implements DbalPreloader
{
    /**
     * @var array<class-string, array<string, array>>
     */
    private array $cache = [];

    public function set(string $modelClass, string $identifier, array $data): void
    {
        $this->cache[$modelClass][$identifier] = $data;
    }

    public function has(string $modelClass, string $identifier): bool
    {
        return isset($this->cache[$modelClass][$identifier]);
    }

    public function get(string $modelClass, string $identifier): ?array
    {
        return $this->cache[$modelClass][$identifier] ?? null;
    }

    public function remove(string $modelClass, string $identifier): void
    {
        unset($this->cache[$modelClass][$identifier]);
    }

    public function clear(string $modelClass): void
    {
        unset($this->cache[$modelClass]);
    }
}
