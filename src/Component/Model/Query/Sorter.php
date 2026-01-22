<?php

namespace Cortex\Component\Model\Query;

class Sorter
{
    public function __construct(
        public readonly string $field,
        public readonly SortDirection $direction = SortDirection::ASC,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('%s_%s', $this->field, $this->direction->value);
    }
}
