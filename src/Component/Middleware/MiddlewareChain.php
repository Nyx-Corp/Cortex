<?php

namespace Cortex\Component\Middleware;

use Cortex\Component\Collection\AsyncCollection;

final class MiddlewareChain
{
    /**
     * @var Middleware[]
     */
    private array $middlewares;

    /**
     * @param Middleware[] $middlewares
     */
    public function __construct(Middleware ...$middlewares)
    {
        usort(
            $middlewares,
            fn (Middleware $a, Middleware $b) => $b->priority <=> $a->priority
        );

        $this->middlewares = array_values($middlewares);
    }

    public function run(...$args): \Generator
    {
        $next = null;

        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = (clone $middleware)->wrap($next, $args);
        }

        return $next(...$args);
    }

    public function compile(...$args): AsyncCollection
    {
        return AsyncCollection::create(
            $this->run(...$args)
        );
    }
}
