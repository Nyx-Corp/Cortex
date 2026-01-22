<?php

namespace Cortex\ValueObject;

class SecurityToken extends ValueObject
{
    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
