<?php

namespace App\Enum\Coupon;

use App\Enum\ListsTrait;

enum Type: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';

    use ListsTrait;
}
