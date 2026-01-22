<?php

namespace Cortex\Bridge\Symfony\Model\Attribute;

use Cortex\Component\Collection\AsyncCollection;
use Cortex\ValueObject\RegisteredClass;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Model
{
    public readonly RegisteredClass $model;
    public readonly RegisteredClass $collection;

    public function __construct(
        string $model,
        ?string $collection = null,
    ) {
        $this->model = new RegisteredClass($model);

        $this->collection = new RegisteredClass(
            $collection ?? RegisteredClass::exists($model.'Collection')
                ? $model.'Collection'
                : AsyncCollection::class
        );
    }
}
