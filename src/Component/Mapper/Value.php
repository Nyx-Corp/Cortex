<?php

namespace Cortex\Component\Mapper;

enum Value: string
{
    case Ignore = 'ignore';
    case Json = 'json';
    case Date = 'date';
    case Bool = 'bool';
}
