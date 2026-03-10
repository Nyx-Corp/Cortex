<?php

namespace Cortex\Component\Security\Action\CreatePassword;

use Cortex\Component\Security\Model\Account;

class Response
{
    public function __construct(
        public readonly Account $account,
    ) {
    }
}
