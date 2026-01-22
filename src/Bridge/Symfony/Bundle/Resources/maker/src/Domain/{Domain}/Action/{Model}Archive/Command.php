<?php

namespace Domain\{Domain}\Action\{Model}Archive;

use Domain\{Domain}\Model\{Model};

class Command
{
    public function __construct(
        public readonly {Model} ${model},
        public readonly bool $isArchived = true,
    ) {
    }
}
