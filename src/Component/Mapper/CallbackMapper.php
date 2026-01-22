<?php

namespace Cortex\Component\Mapper;

class CallbackMapper implements Mapper
{
    public function __construct(
        private readonly \Closure $mapper,
    ) {
    }

    public function map($source, &$dest = [], ...$context): array
    {
        return ($this->mapper)($source, $dest, ...$context);
    }
}
