<?php

namespace Cortex\Bridge\Symfony\Model\Query;

use Cortex\Component\Model\Query\Factory\FlatQueryFactory;
use Cortex\ValueObject\RegisteredClass;

class RequestQueryFactory extends FlatQueryFactory
{
    protected function getQueryClass(): RegisteredClass
    {
        return new RegisteredClass(ModelQueryDecorator::class);
    }
}
