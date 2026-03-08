<?php

namespace Cortex\Bridge\Symfony\Security;

use Cortex\Component\Security\Service\PasswordHasherInterface;
use Cortex\ValueObject\HashedPassword;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private readonly PasswordHasherFactoryInterface $factory,
    ) {
    }

    public function hashPassword(#[\SensitiveParameter] string $plainPassword): HashedPassword
    {
        $hasher = $this->factory->getPasswordHasher(PasswordAuthenticatedUserInterface::class);

        return new HashedPassword($hasher->hash($plainPassword));
    }
}
