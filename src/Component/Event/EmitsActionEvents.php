<?php

namespace Cortex\Component\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

trait EmitsActionEvents
{
    private ?EventDispatcherInterface $eventDispatcher = null;

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->eventDispatcher = $dispatcher;
    }

    protected function emit(object $event): object
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }
}
