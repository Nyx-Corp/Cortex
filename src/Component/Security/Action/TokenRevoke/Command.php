<?php

namespace Cortex\Component\Security\Action\TokenRevoke;

use Cortex\Component\Security\Model\Token;

class Command
{
    public function __construct(
        public readonly Token $token,
    ) {
    }
}
