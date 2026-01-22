<?php

namespace Cortex\Component\Model\Factory;

use Cortex\Component\Collection\StructuredMap;
use Cortex\ValueObject\RegisteredClass;

final class CreationCommand
{
    public function __construct(
        public readonly RegisteredClass $modelClass,
        public readonly StructuredMap $arguments,
    ) {
    }
}
