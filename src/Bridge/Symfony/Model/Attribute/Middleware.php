<?php

namespace Cortex\Bridge\Symfony\Model\Attribute;

use Cortex\Component\Model\Scope;
use Cortex\ValueObject\RegisteredClass;

/**
 * @Annotation
 * @Target("CLASS")
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Middleware
{
    public readonly RegisteredClass $class;

    public function __construct(
        string $modelClass,
        public readonly Scope $on = Scope::All,
        public readonly string $handler = '__invoke',
        public readonly int $priority = 10,
    ) {
        $this->class = new RegisteredClass($modelClass);
    }
}
