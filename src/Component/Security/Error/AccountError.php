<?php

namespace Cortex\Component\Security\Error;

enum AccountError: string
{
    case InvalidUsername = 'account.error.invalid_username';
    case InvalidPassword = 'account.error.invalid_password';
    case UsernameAlreadyExists = 'account.error.username_already_exists';
    case InvalidToken = 'account.error.invalid_token';
    case TokenExpired = 'account.error.token_expired';
}
