<?php

namespace Cortex\Component\Middleware;

class Middleware
{
    private $handler;

    public private(set) ?\Closure $next = null;
    public bool $isLast {
        get => null === $this->next;
    }

    public function __construct(
        object|array|callable $handler,
        public readonly int $priority = 100,
    ) {
        if (!is_callable($handler)) {
            throw new \InvalidArgumentException('Handler must be callable.');
        }

        $this->handler = $handler;
    }

    public function next(): \Generator
    {
        return yield from $this->isLast ? [] : ($this->next)();
    }

    public function wrap(?self $next, array $args): self
    {
        $this->next = $next
            ? fn () => $next(...$args)
            : null;

        return $this;
    }

    public function __invoke(...$args): \Generator
    {
        return call_user_func($this->handler, $this, ...$args);
    }
}
