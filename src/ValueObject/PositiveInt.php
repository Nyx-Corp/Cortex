<?php

namespace Cortex\ValueObject;

final class PositiveInt extends ValueObject
{
    public function __construct(int|PositiveInt $value)
    {
        if (!is_int($value)) {
            $this->value = $value->value;

            return;
        }

        if ($value < 0) {
            throw new \InvalidArgumentException(sprintf('Value has to be positive, %d given.', $this->value));
        }

        $this->value = $value;
    }
}
