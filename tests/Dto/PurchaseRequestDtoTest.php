<?php

namespace App\Tests\Dto;

use App\Dto\PurchaseRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class PurchaseRequestDtoTest extends TestCase
{
    public function testCreateFromRequest(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'DE123456789',
            'couponCode' => 'SUMMER10',
            'paymentProcessor' => 'paypal',
            'amount' => 150.0
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));
        $dto = new PurchaseRequestDto($request);

        $this->assertEquals(1, $dto->product);
        $this->assertEquals('DE123456789', $dto->taxNumber);
        $this->assertEquals('SUMMER10', $dto->couponCode);
        $this->assertEquals('paypal', $dto->paymentProcessor);
        $this->assertEquals(150.0, $dto->amount);
    }
}