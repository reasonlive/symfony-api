<?php

namespace App\Dto;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

class CalculatePriceRequestDto
{
    /** @var int ID of product */
    #[Assert\NotBlank]
    #[Assert\Positive(message: "Product ID must be a positive number")]
    public ?int $product;

    /** @var string Country Tax number from buying side */
    #[Assert\NotBlank]
    public ?string $taxNumber;

    /** @var string|null discount coupon code if exist */
    public ?string $couponCode;

    public function __construct(Request $request) {
        $data = json_decode($request->getContent());

        $this->product = $data->product ?? null;
        $this->taxNumber = $data->taxNumber ?? null;
        $this->couponCode = $data->couponCode ?? null;
    }
}