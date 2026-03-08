<?php

namespace Cortex\Component\Security\Action\AskPasswordReset;

use Cortex\Component\Security\Model\Token;

class Response
{
    public function __construct(
        public readonly ?Token $token,
        public readonly ?string $rawToken = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return null !== $this->token;
    }
}
