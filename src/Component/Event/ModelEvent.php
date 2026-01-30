<?php

namespace Cortex\Component\Event;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class ModelEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;
    protected object $response;

    public function __construct(object $response)
    {
        $this->response = $response;
    }

    public function getResponse(): object
    {
        return $this->response;
    }

    public function setResponse(object $response): void
    {
        $this->response = $response;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
