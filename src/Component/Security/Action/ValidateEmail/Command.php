<?php

namespace Cortex\Component\Security\Action\ValidateEmail;

use Cortex\Component\Security\Model\Token;

class Command
{
    public function __construct(
        public readonly Token $token,
        public readonly string $rawToken,
    ) {
    }
}
