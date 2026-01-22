<?php

namespace Cortex\Component\Model\Factory\Mapper;

use Cortex\Component\Model\Factory\ModelPrototype;

interface ModelMapper
{
    public function prototype(ModelPrototype $prototype, array $modelData): ?ModelPrototype;

    // public function normalize($model) : array;
}
