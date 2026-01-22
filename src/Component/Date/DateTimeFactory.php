<?php

namespace Cortex\Component\Date;

class DateTimeFactory
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
