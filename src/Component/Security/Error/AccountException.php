<?php

namespace Cortex\Component\Security\Error;

use Cortex\Component\Exception\DomainException;

class AccountException extends \RuntimeException implements DomainException
{
    public function __construct(
        public readonly AccountError $error,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($error->value, 0, $previous);
    }

    public function getDomain(): string
    {
        return 'security';
    }
}
