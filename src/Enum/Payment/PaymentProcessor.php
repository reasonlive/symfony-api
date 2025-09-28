<?php

namespace App\Enum\Payment;

enum PaymentProcessor: string
{
    case PAYPAL = 'paypal';
    case STRIPE = 'stripe';
}