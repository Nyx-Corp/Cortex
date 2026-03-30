<?php

declare(strict_types=1);

namespace Cortex\Bridge\Symfony\Serializer\Event;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Dispatched before denormalizing data into model data (array → model).
 *
 * Listeners can modify the group or stop propagation.
 */
class PreDenormalizeEvent implements StoppableEventInterface
{
    private string $group;
    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $modelClass,
        public readonly array $data,
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
