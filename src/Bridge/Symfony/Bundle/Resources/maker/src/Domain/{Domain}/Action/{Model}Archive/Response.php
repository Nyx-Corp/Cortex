<?php

namespace Domain\{Domain}\Action\{Model}Archive;

use Domain\{Domain}\Model\{Model};

class Response
{
    public function __construct(
        public readonly {Model} ${model},
        public readonly bool $success = true,
    ) {
    }
}
