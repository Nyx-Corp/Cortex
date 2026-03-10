<?php

namespace Cortex\Component\Security\Service;

use Cortex\ValueObject\HashedPassword;

interface PasswordHasherInterface
{
    public function hashPassword(
        #[\SensitiveParameter]
        string $plainPassword,
    ): HashedPassword;
}
