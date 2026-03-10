<?php

namespace Cortex\Bridge\Symfony\Form\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Action
{
    public function __construct(
        public readonly string $commandClass,
    ) {
    }
}
