<?php

namespace Cortex\Component\Security\Factory;

use Cortex\Component\Model\Attribute\Model;
use Cortex\Component\Model\Factory\ModelFactory;
use Cortex\Component\Security\Model\Token;

#[Model(Token::class)]
class TokenFactory extends ModelFactory
{
}
