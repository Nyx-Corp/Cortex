<?php

namespace Cortex\Component\Collection;

use function Symfony\Component\String\u;

class StructuredMap implements \IteratorAggregate, \Countable
{
    protected array $keys = [];
    protected array $keyMap = [];

    protected array $elements = [];

    protected array $validation = [];
    protected array $nullables = [];

    public function __construct(
        array $elements = [],
        array $validation = [],
        array $nullables = [],
    ) {
        foreach ($validation as $key => $validation) {
            $nullableIndex = array_search($key, $nullables);
            if (false !== $nullableIndex) {
                unset($nullables[$nullableIndex]);
            }

            $this->declare($key, $validation, false !== $nullableIndex);
        }

        foreach ($nullables as $nullable) {
            $this->declare($nullable, nullable: true);
        }

        foreach ($elements as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function declare(string $key, ?callable $validation = null, bool $nullable = false): self
    {
        $this->keys[$key] = true;

        $this->keyMap[$key] = $key;
        $this->keyMap[(string) u($key)->camel()] = $key;
        $this->keyMap[(string) u($key)->snake()] = $key;

        if (!is_null($validation)) {
            $this->validation[$key] = $validation;
        }

        if ($nullable) {
            $this->nullables[$key] = true;
        }

        // validate key if already defined
        return array_key_exists($key, $this->elements) ?
            $this->validate($key, $this->elements[$key]) :
            $this
        ;
    }

    public function declaredKeys(): array
    {
        return array_keys($this->keys);
    }

    public function hasDeclaredKey(string $key): bool
    {
        return isset($this->keyMap[$key]);
    }

    public function has($key): bool
    {
        return !empty($this->keyMap[$key]);
    }

    private function assertMappedKey(string $key): string
    {
        if (!$this->has($key)) {
            throw new \OutOfBoundsException();
        }

        return $this->keyMap[$key];
    }

    public function validate(string $key, mixed $value): self
    {
        $mappedKey = $this->assertMappedKey($key);

        if (null === $value) {
            if (!isset($this->nullables[$mappedKey]) || false === $this->nullables[$mappedKey]) {
                throw new \InvalidArgumentException('Not null');
            }

            return $this;
        }

        if (!array_key_exists($mappedKey, $this->validation)) {
            return $this;
        }

        if (!($this->validation[$mappedKey])($value)) {
            throw new \InvalidArgumentException(sprintf('Validation failed for key "%s" : "%s" given.', $mappedKey, get_debug_type($value)));
        }

        return $this;
    }

    public function set(string $key, $value): self
    {
        if (is_null($this->keyMap[$key] ?? null)) {
            $this->declare($key, nullable: is_null($value));
        }

        $this->validate($key, $value);

        $this->elements[$key] = $value;

        return $this;
    }

    public function add(string $key, array $values): self
    {
        if (is_null($this->keyMap[$key] ?? null)) {
            $this->declare($key, nullable: empty($values));
        }

        $this->elements[$key] = array_filter(
            $values,
            fn ($value) => $this->validate($key, $value)
        );

        return $this;
    }

    public function unset(string $key): self
    {
        $mappedKey = $this->assertMappedKey($key);

        unset($this->elements[$mappedKey]);
        unset($this->keys[$mappedKey]);
        unset($this->validation[$mappedKey]);
        unset($this->nullables[$mappedKey]);

        foreach ($this->keyMap as $alias => $original) {
            if ($original === $mappedKey) {
                unset($this->keyMap[$alias]);
            }
        }

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->elements[$this->assertMappedKey($key)] ?? $default;
    }

    public function all(): array
    {
        return $this->elements;
    }

    public function map(callable $mapper)
    {
        return array_map($mapper, $this->elements);
    }

    public function merge(array|self $elements): self
    {
        $elements = $elements instanceof self ? $elements->all() : $elements;
        foreach ($elements as $key => $item) {
            $this->set($key, $item);
        }

        return $this;
    }

    public function prototype(): self
    {
        $prototype = new self();
        $prototype->keys = $this->keys;
        $prototype->keyMap = $this->keyMap;
        $prototype->validation = $this->validation;
        $prototype->nullables = $this->nullables;

        return $prototype;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->all());
    }

    public function count(): int
    {
        return count($this->elements);
    }

    public function keys(): array
    {
        return array_keys($this->elements);
    }

    public function values(): array
    {
        return array_values($this->elements);
    }
}
