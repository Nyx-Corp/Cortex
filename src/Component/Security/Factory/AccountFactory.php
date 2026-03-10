<?php

namespace Cortex\Component\Security\Factory;

use Cortex\Component\Model\Attribute\Model;
use Cortex\Component\Model\Factory\ModelFactory;
use Cortex\Component\Security\Model\Account;

#[Model(Account::class)]
class AccountFactory extends ModelFactory
{
}
