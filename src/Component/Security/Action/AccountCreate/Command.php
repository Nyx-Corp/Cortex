<?php

namespace Cortex\Component\Security\Action\AccountCreate;

use Cortex\ValueObject\Email;

class Command
{
    public function __construct(
        public readonly Email $username,
        public readonly ?string $plainPassword = null,
        public readonly ?array $acl = null,
    ) {
    }
}
