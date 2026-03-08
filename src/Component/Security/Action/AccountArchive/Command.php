<?php

namespace Cortex\Component\Security\Action\AccountArchive;

use Cortex\Component\Security\Model\Account;

class Command
{
    public function __construct(
        public readonly Account $account,
        public readonly bool $isArchived = true,
    ) {
    }
}
