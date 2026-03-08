<?php

namespace Cortex\Bridge\Symfony\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Projects implement this interface to resolve a raw Bearer token
 * into a Symfony UserInterface (typically via TokenHasher + TokenStore).
 */
interface ApiTokenProviderInterface
{
    public function findUserByToken(string $rawToken): ?UserInterface;
}
