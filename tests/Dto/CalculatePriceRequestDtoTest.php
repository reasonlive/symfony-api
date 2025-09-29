<?php

namespace App\Tests\Dto;

use App\Dto\CalculatePriceRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CalculatePriceRequestDtoTest extends TestCase
{
    public function testCreateFromRequest(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'DE123456789',
            'couponCode' => 'SUMMER10'
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));
        $dto = new CalculatePriceRequestDto($request);

        $this->assertEquals(1, $dto->product);
        $this->assertEquals('DE123456789', $dto->taxNumber);
        $this->assertEquals('SUMMER10', $dto->couponCode);
    }

    public function testCreateFromRequestWithNullValues(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'DE123456789'
            // couponCode is missing
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));
        $dto = new CalculatePriceRequestDto($request);

        $this->assertEquals(1, $dto->product);
        $this->assertEquals('DE123456789', $dto->taxNumber);
        $this->assertNull($dto->couponCode);
    }
}