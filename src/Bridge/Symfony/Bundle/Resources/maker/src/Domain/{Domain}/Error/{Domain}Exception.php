<?php

namespace Domain\{Domain}\Error;

use Cortex\Component\Exception\DomainException;

/**
 * Base exception for {Domain} domain.
 */
class {Domain}Exception extends \Exception implements DomainException
{
    public function getDomain(): string
    {
        return '{domain}';
    }
}
