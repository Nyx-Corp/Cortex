<?php

namespace Cortex\Component\Model;

use Cortex\Component\Collection\AsyncCollection;
use Cortex\Component\Model\Query\ModelQuery;

class ModelCollection extends AsyncCollection
{
    public private(set) ?ModelQuery $query = null;

    public static function build(ModelQuery $query): static
    {
        // Passe une closure au lieu du Generator directement pour lazy resolution
        $collection = static::create(fn () => $query->resolve());
        $collection->query = $query;

        return $collection;
    }

    protected function onNext(AsyncCollection $nextInstance): void
    {
        if (!$nextInstance instanceof self) {
            throw new \BadMethodCallException('Can only be called from ModelCollections.');
        }

        $nextInstance->query = $this->query;
    }
}
