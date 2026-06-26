<?php

namespace App\Enums\PhoneNumbers;

enum PhoneNumberType: string
{
    case Did = 'did';
    case TollFree = 'toll_free';
    case Mobile = 'mobile';
    case National = 'national';
    case Local = 'local';
    case InternalAlias = 'internal_alias';
}
