<?php

namespace Cortex\ValueObject;

readonly class CurrentDate
{
    public function __construct(
        public \DateTimeImmutable $value = new \DateTimeImmutable(),
    ) {
    }
}
