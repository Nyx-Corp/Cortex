<?php

namespace Cortex\Bridge\Symfony\Api;

interface VersionTransformerInterface
{
    /**
     * Returns the command class this transformer handles.
     */
    public function getCommandClass(): string;

    /**
     * Transforms an incoming request payload from a specific API version
     * to the current internal format.
     */
    public function transformRequest(array $data, int $fromVersion): array;

    /**
     * Transforms an outgoing response from the internal format
     * to match a specific API version.
     */
    public function transformResponse(mixed $data, int $toVersion): mixed;
}
