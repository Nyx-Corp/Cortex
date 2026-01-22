<?php

namespace Cortex\Component\Date;

class DateTimeImmutable extends \DateTimeImmutable implements \Stringable
{
    public function __toString(): string
    {
        return $this->format('Y-m-d H:i:s');
    }
}
