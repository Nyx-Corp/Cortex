<?php

namespace Cortex\Component\Security\Action\AccountUpdate;

use Cortex\ValueObject\Email;
use Symfony\Component\Uid\Uuid;

class Command
{
    public function __construct(
        public readonly Uuid $uuid,
        public readonly Email $username,
        public readonly ?string $plainPassword = null,
        public readonly ?array $acl = null,
    ) {
    }
}
