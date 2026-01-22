<?php

namespace Cortex\Component\Collection;

use Cortex\ValueObject\RegisteredClass;

/**
 * @template T
 *
 * @implements \Iterator<int|string, T>
 */
class AsyncCollection implements \Iterator, \Countable, \JsonSerializable
{
    /** @var array<int|string, T> */
    private array $elements = [];

    private ?\Generator $origin;
    private ?\Generator $innerIterator = null;

    public static function create(iterable|callable $origin = []): static
    {
        // Ne PAS exécuter le callable ici !
        $collection = new static($origin);
        $expectedType = static::expectedType();

        return $expectedType === null ? $collection :
            $collection->filter(fn (mixed $element) => $expectedType->assertInstanceOf($element))
        ;
    }

    protected static function expectedType(): ?RegisteredClass
    {
        return null;
    }

    final private function __construct(iterable|callable $origin = [])
    {
        $this->origin = $origin instanceof \Generator ? $origin : self::wrap($origin);
    }

    private static function wrap(iterable|callable $collection): \Generator
    {
        if (is_callable($collection)) {
            $result = $collection();

            // Valider que le callable retourne bien un iterable
            if (!is_iterable($result)) {
                throw new \InvalidArgumentException(sprintf('Callable passed to AsyncCollection must return an iterable, %s returned.', is_object($result) ? get_class($result) : gettype($result)));
            }

            yield from $result;
        } else {
            foreach ($collection as $key => $value) {
                yield $key => $value;
            }
        }
    }

    /**
     * Casts the current collection into another collection class.
     *
     * @param class-string<static> $collectionClass
     */
    public function as(string|RegisteredClass $collectionClass): static
    {
        if ((string) $collectionClass === static::class) {
            return $this;
        }

        // Délègue à then() avec changement de classe
        return $this->then($this, $collectionClass);
    }

    /**
     * Hook appelé après la création d'une nouvelle instance via then()
     * Permet aux classes filles de copier leurs propriétés.
     */
    protected function onNext(AsyncCollection $nextInstance): void
    {
        // Par défaut, ne fait rien
    }

    public function then(iterable|callable $next, string|RegisteredClass|null $targetClass = null): static
    {
        $nextIterable = is_callable($next) ? $next($this) : $next;

        if (is_null($targetClass)) {
            $targetClass = static::class;
        }
        if (is_string($targetClass)) {
            $targetClass = new RegisteredClass($targetClass);
        }

        $targetClass->assertIsInstanceOf(static::class);

        $this->onNext($nextInstance = new ((string) $targetClass)($nextIterable));

        return $nextInstance;
    }

    private function getIterator(): \Generator
    {
        yield from $this->elements;

        while ($this->origin?->valid()) {
            $element = $this->origin->current();
            $key = $this->origin->key();

            $this->elements[$key] = $element;
            yield $key => $element;

            $this->origin->next();
        }

        $this->origin = null;
    }

    private function getInnerIterator(): \Generator
    {
        return $this->innerIterator ??= $this->getIterator();
    }

    public function rewind(): void
    {
        $this->innerIterator = $this->getIterator();
    }

    public function current(): mixed
    {
        return $this->getInnerIterator()->current();
    }

    public function key(): mixed
    {
        return $this->getInnerIterator()->key();
    }

    public function next(): void
    {
        $this->getInnerIterator()->next();
    }

    public function valid(): bool
    {
        if (!$this->getInnerIterator()->valid()) {
            $this->origin = null;
            $this->innerIterator = null;

            return false;
        }

        return true;
    }

    /**
     * @param callable(T, int|string): mixed $mapper
     */
    public function map(callable $mapper): static
    {
        return $this->then(
            fn (self $origin) => (function () use ($origin, $mapper) {
                foreach ($origin as $key => $element) {
                    yield $key => $mapper($element, $key);
                }
            })()
        );
    }

    /**
     * @param callable(T, int|string): bool $filter
     */
    public function filter(callable $filter): static
    {
        return $this->then(
            fn (self $origin) => (function () use ($origin, $filter) {
                foreach ($origin as $key => $element) {
                    if ($filter($element, $key)) {
                        yield $key => $element;
                    }
                }
            })()
        );
    }

    /**
     * @return array<int|string, T>
     */
    public function toArray(): array
    {
        $data = iterator_to_array($this->getIterator());
        $this->innerIterator = null;

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function count(): int
    {
        return count($this->toArray());
    }

    public function first(): mixed
    {
        foreach ($this->getIterator() as $element) {
            return $element;
        }

        return null;
    }

    /**
     * @param callable(mixed, T, int|string): mixed $reducer
     */
    public function reduce(callable $reducer, mixed $initial = null): mixed
    {
        $accumulator = $initial;
        foreach ($this as $key => $element) {
            $accumulator = $reducer($accumulator, $element, $key);
        }

        return $accumulator;
    }

    /**
     * @param callable(T, int|string): \Generator<mixed> $callback
     */
    public function each(callable $callback): static
    {
        return $this->then(
            function (self $origin) use ($callback) {
                $index = 0;
                foreach ($origin as $key => $element) {
                    foreach ($callback($element, $key) as $subKey => $subElement) {
                        $safeKey = is_int($subKey) ? $index++ : $subKey;
                        yield $safeKey => $subElement;
                    }
                }
            }
        );
    }

    public function at(int $index): mixed
    {
        for ($i = 0; $i <= $index; ++$i) {
            if (!$this->valid()) {
                return null;
            }

            if ($i === $index) {
                return $this->current();
            }

            $this->next();
        }

        return null;
    }

    /**
     * @template TReturn
     *
     * @param callable(self): bool                   $condition
     * @param callable(self): iterable<TReturn>      $then
     * @param callable(self): iterable<TReturn>|null $else
     *
     * @return static<TReturn>
     */
    public function if(callable $condition, callable $then, ?callable $else = null): static
    {
        return $this->then(
            function (self $origin) use ($condition, $then, $else) {
                yield from $condition($origin)
                    ? $then($origin)
                    : ($else ? $else($origin) : $origin->getIterator())
                ;
            }
        );
    }

    /**
     * @template TReturn
     *
     * @param callable(self): iterable<TReturn>      $then
     * @param callable(self): iterable<TReturn>|null $else
     *
     * @return static<TReturn>
     */
    public function ifEmpty(callable $then, ?callable $else = null): static
    {
        return $this->if(
            fn (self $origin) => $origin->count() === 0,
            $then,
            $else
        );
    }

    /**
     * Finds the first element matching the predicate.
     *
     * @param callable(T, int|string): bool $predicate
     */
    public function find(callable $predicate): mixed
    {
        return $this->filter($predicate)->first();
    }

    public function join(string $joiner): string
    {
        return implode(
            $joiner,
            $this
                ->map(fn (mixed $element) => (string) $element)
                ->toArray()
        );
    }
}
