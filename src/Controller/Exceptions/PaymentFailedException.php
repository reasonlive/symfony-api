<?php

namespace App\Controller\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaymentFailedException extends UnprocessableEntityHttpException
{
    public function __construct(string $message = null)
    {
        parent::__construct($message ?? 'Payment failed, try again later');
    }
}