<?php

namespace Cortex\Component\Security\Action\AccountCreate;

use Cortex\Component\Security\Model\Account;

class Response
{
    public function __construct(
        public readonly Account $account,
    ) {
    }
}
