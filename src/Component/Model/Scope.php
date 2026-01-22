<?php

namespace Cortex\Component\Model;

enum Scope: string
{
    case Fetch = 'fetch';
    case Create = 'create';
    case Sync = 'sync';
    case Remove = 'remove';
    case All = 'all'; // alias for all scopes
}
