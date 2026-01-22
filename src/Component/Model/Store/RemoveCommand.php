<?php

namespace Cortex\Component\Model\Store;

use Cortex\ValueObject\RegisteredClass;

final class RemoveCommand
{
    public function __construct(
        public readonly object $model,
        public RegisteredClass $modelClass,
    ) {
        $modelClass->assertInstanceOf($model);
    }
}
