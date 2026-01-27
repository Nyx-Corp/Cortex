<?php

namespace Cortex\Component\Exception;

/**
 * Interface for domain exceptions.
 *
 * Allows controllers to catch all domain exceptions and get the translation domain.
 */
interface DomainException extends \Throwable
{
    /**
     * Get the translation domain for this exception's message.
     */
    public function getDomain(): string;
}
