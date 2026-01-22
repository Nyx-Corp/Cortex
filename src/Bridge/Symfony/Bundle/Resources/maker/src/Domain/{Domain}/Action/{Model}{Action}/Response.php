<?php

namespace Domain\{Domain}\Action\{Model}{Action};

use Domain\{Domain}\Model\{Model};

class Response
{
    public function __construct(
        public readonly {Model} ${model},
    ) {
    }

    public function isSuccess(): bool
    {
        return true;
    }
}
