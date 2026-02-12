<?php

namespace Cortex\ValueObject;

class CurrentDateFactory
{
    public function create(): CurrentDate
    {
        return new CurrentDate();
    }
}
