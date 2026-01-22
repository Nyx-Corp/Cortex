<?php

namespace Cortex\Component\Model\Query\Factory;

use Cortex\Component\Collection\StructuredMap;
use Cortex\Component\Model\Query\ModelQuery;
use Cortex\ValueObject\RegisteredClass;

/**
 * Empty factory class to allow query decoration.
 */
class FlatQueryFactory implements QueryFactory
{
    protected function getQueryClass(): RegisteredClass
    {
        return new RegisteredClass(ModelQuery::class);
    }

    public function createQuery(
        \Closure $resolver,
        RegisteredClass $modelCollectionClass,
        ?StructuredMap $filters = null,
        ?StructuredMap $tags = null,
    ): ModelQuery {
        return new ((string) $this->getQueryClass())(
            $resolver,
            $modelCollectionClass,
            $filters,
            $tags
        );
    }
}
