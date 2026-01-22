<?php

namespace Cortex\Component\Mapper;

enum Strategy
{
    case AutoMapCamel;  // map in camel
    case AutoMapSnake;  // map in snake

    case AutoMapNone;   // only given keys
    case AutoMapAll;    // all keys
}
