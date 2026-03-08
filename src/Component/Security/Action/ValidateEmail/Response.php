<?php

namespace Cortex\Component\Security\Action\ValidateEmail;

use Cortex\Component\Security\Model\Account;

class Response
{
    public function __construct(
        public readonly Account $account,
    ) {
    }
}
