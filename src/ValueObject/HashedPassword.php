<?php

namespace Cortex\ValueObject;

class HashedPassword extends ValueObject
{
    /**
     * Password constructor.
     *
     * @param string $password the password to be validated and stored
     *
     * @throws \InvalidArgumentException if the password is not valid
     */
    public function __construct(
        #[\SensitiveParameter]
        string $password,
    ) {
        $this->value = $password;
    }
}
