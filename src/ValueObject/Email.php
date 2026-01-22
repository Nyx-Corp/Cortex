<?php

namespace Cortex\ValueObject;

/**
 * Email Value Object.
 *
 * This class represents an email address as a value object.
 * It ensures that the email is valid and provides methods for comparison and string representation.
 */
class Email extends ValueObject
{
    /**
     * Email constructor.
     *
     * @param string $email the email address to be validated and stored
     *
     * @throws \InvalidArgumentException if the email address is not valid
     */
    public function __construct(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address: $email");
        }

        $this->value = $email;
    }
}
