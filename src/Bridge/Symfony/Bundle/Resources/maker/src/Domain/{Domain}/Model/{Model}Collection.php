<?php

namespace Domain\{Domain}\Model;

use Cortex\Component\Model\ModelCollection;
use Cortex\ValueObject\RegisteredClass;

class {Model}Collection extends ModelCollection
{
    protected static function expectedType(): ?RegisteredClass
    {
        return new RegisteredClass({Model}::class);
    }
}
