<?php

declare(strict_types=1);

namespace Cortex\Component\Mapper;

/**
 * Default implementation of publicGroups(): 'id' and 'list' are public.
 *
 * Use this trait in ModelRepresentation implementations unless
 * the model requires different public/private boundaries.
 */
trait DefaultPublicGroupsTrait
{
    public function publicGroups(): array
    {
        return ['id', 'list'];
    }
}
