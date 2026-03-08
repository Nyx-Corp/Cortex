<?php

namespace Cortex\Component\Security\Action\TokenCreate;

use Cortex\Component\Security\Model\Account;

class Command
{
    public function __construct(
        public readonly Account $account,
        public readonly string $intention,
        public readonly string $expiresIn = '+30 days',
        public readonly ?string $label = null,
        public readonly ?array $scopes = null,
    ) {
    }
}
