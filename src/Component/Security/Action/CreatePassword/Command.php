<?php

namespace Cortex\Component\Security\Action\CreatePassword;

use Cortex\Component\Security\Model\Token;

class Command
{
    public function __construct(
        public readonly Token $token,
        public readonly string $rawToken,
        #[\SensitiveParameter]
        public readonly string $plainPassword,
    ) {
    }
}
