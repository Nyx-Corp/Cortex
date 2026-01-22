<?php

namespace Domain\{Domain}\Action\{Model}Edit;

use Domain\{Domain}\Model\{Model};
use Symfony\Component\Uid\Uuid;

class Command
{    
    /**
     * Define editable properties here
     */
    public function __construct(
        // public readonly string $label,
        public readonly ?Uuid $uuid = null,
    ) {
    }
}
