<?php

namespace Cortex\Component\Security\Action\TokenCreate;

use Cortex\Component\Security\Model\Token;

class Response
{
    public function __construct(
        public readonly Token $token,
        public readonly string $rawToken,
    ) {
    }
}
