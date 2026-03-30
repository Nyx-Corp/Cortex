<?php

namespace Cortex\ValueObject;

abstract class ValueObject implements \Stringable
{
    public readonly mixed $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function equals($other): bool
    {
        return is_a($other::class, static::class, true)
            && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function __invoke()
    {
        return $this->value;
    }
}
