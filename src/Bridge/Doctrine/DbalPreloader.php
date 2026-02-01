<?php

declare(strict_types=1);

namespace Cortex\Bridge\Doctrine;

/**
 * Interface for preloading data from JOINs to avoid N+1 queries.
 *
 * When a parent mapper (e.g., ClubMapper) does a JOIN with a related table,
 * it can preload the related data. The related mapper then checks the preloader
 * before executing a query.
 *
 * @example
 *     // In ClubMapper after JOIN query:
 *     $this->preloader->set(Organisation::class, $uuid, $orgData);
 *
 *     // In OrganisationMapper:
 *     if ($this->preloader->has(Organisation::class, $uuid)) {
 *         $data = $this->preloader->get(Organisation::class, $uuid);
 *         // use cached data instead of querying
 *     }
 */
interface DbalPreloader
{
    /**
     * Store preloaded data for a model.
     *
     * @param class-string $modelClass The model class (e.g., Organisation::class)
     * @param string       $identifier The unique identifier (usually UUID)
     * @param array        $data       The raw table data to cache
     */
    public function set(string $modelClass, string $identifier, array $data): void;

    /**
     * Check if data is preloaded for a model.
     *
     * @param class-string $modelClass The model class
     * @param string       $identifier The unique identifier
     */
    public function has(string $modelClass, string $identifier): bool;

    /**
     * Get preloaded data if available.
     *
     * @param class-string $modelClass The model class
     * @param string       $identifier The unique identifier
     *
     * @return array|null The cached data or null if not found
     */
    public function get(string $modelClass, string $identifier): ?array;

    /**
     * Remove preloaded data after use.
     *
     * @param class-string $modelClass The model class
     * @param string       $identifier The unique identifier
     */
    public function remove(string $modelClass, string $identifier): void;

    /**
     * Clear all preloaded data for a model class.
     *
     * @param class-string $modelClass The model class
     */
    public function clear(string $modelClass): void;
}
