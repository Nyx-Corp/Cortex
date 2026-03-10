<?php

namespace Cortex\Component\Security\Action\AskPasswordReset;

use Cortex\ValueObject\Email;

class Command
{
    public function __construct(
        public readonly Email $email,
    ) {
    }
}
