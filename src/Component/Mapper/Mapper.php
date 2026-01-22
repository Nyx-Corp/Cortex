<?php

namespace Cortex\Component\Mapper;

interface Mapper
{
    /**
     * Maps the source to a destination array based on the defined mapping.
     *
     * @param mixed $source     the source data to map
     * @param mixed $dest       reference on destination
     * @param mixed ...$context Optional context parameters for mapping.
     *
     * @return array the mapped destination array
     */
    public function map($source, &$dest = [], ...$context): array;
}
