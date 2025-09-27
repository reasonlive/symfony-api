<?php

namespace App\Enum\Coupon;

use App\Enum\ListsTrait;

enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case EXPIRED = 'expired';

    use ListsTrait;
}