<?php

namespace Cortex\Component\Model\Query\Factory;

use Cortex\Component\Collection\StructuredMap;
use Cortex\Component\Model\Query\ModelQuery;
use Cortex\ValueObject\RegisteredClass;

interface QueryFactory
{
    public function createQuery(
        \Closure $resolver,
        RegisteredClass $modelCollectionClass,
        ?StructuredMap $filters = null,
        ?StructuredMap $tags = null,
    ): ModelQuery;
}
