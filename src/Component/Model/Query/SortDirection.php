<?php

namespace Cortex\Component\Model\Query;

enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';

    public static function fromString(string $direction): self
    {
        return match (strtolower($direction)) {
            'asc' => self::ASC,
            'desc' => self::DESC,
            default => self::ASC, // Default to ASC if invalid direction is provided
        };
    }
}
