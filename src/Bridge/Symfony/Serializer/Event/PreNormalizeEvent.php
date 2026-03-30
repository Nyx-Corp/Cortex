<?php

declare(strict_types=1);

namespace Cortex\Bridge\Symfony\Serializer\Event;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Dispatched before normalizing a domain object (model → array).
 *
 * Listeners can modify the group or stop propagation.
 */
class PreNormalizeEvent implements StoppableEventInterface
{
    private string $group;
    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $modelClass,
        public readonly object $data,
        string $group,
    ) {
        $this->group = $group;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function setGroup(string $group): void
    {
        $this->group = $group;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
