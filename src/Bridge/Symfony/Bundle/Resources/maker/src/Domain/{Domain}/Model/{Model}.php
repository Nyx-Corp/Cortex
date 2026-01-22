<?php

namespace Domain\{Domain}\Model;

use Cortex\Component\Model\Archivable;
use Cortex\Component\Model\Uuidentifiable;
use Symfony\Component\Uid\Uuid;

class {Model} implements \Stringable
{
    use Uuidentifiable;
    use Archivable;

    public function __construct(
        // public readonly mixed $property
        ?Uuid $uuid = null,
    ) {
        $this->uuid = $uuid;
    }

    public function __toString(): string
    {
        return (string) $this->uuid;
    }
}
