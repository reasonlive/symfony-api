<?php

namespace App\Enum\Order;

use App\Enum\ListsTrait;

enum Status: string
{
    case NEW = 'new';
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    use ListsTrait;
}
