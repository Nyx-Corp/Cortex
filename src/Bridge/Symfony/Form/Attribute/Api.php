<?php

namespace Cortex\Bridge\Symfony\Form\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Api
{
    public function __construct(
        public readonly int $since = 1,
        public readonly ?int $deprecated = null,
        public readonly ?string $sunset = null,
    ) {
    }

    public function isAvailableIn(int $version): bool
    {
        return $version >= $this->since;
    }

    public function isDeprecatedIn(int $version): bool
    {
        return $this->deprecated !== null && $version >= $this->deprecated;
    }
}
