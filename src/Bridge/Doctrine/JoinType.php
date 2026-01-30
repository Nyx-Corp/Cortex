<?php

declare(strict_types=1);

namespace Cortex\Bridge\Doctrine;

/**
 * Enum representing SQL JOIN types.
 */
enum JoinType: string
{
    case Inner = 'INNER JOIN';
    case Left = 'LEFT JOIN';
    case Right = 'RIGHT JOIN';
}
