<?php

namespace Cortex\Component\Security\Persistence;

use Cortex\Component\Model\Attribute\Model;
use Cortex\Component\Model\Store\ModelStore;
use Cortex\Component\Security\Model\Token;

#[Model(Token::class)]
class TokenStore extends ModelStore
{
}
