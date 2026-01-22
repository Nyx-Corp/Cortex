<?php

namespace Cortex\Component\Collection;

class CallableCollection extends StructuredMap
{
    public function __construct(callable ...$callables)
    {
        parent::__construct($callables);
    }
}
