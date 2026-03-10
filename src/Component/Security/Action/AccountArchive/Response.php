<?php

namespace Cortex\Component\Security\Action\AccountArchive;

use Cortex\Component\Security\Model\Account;

class Response
{
    public function __construct(
        public readonly Account $account,
        public readonly bool $isSuccess = true,
    ) {
    }
}
