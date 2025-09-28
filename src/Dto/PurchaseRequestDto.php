<?php

namespace App\Dto;
use App\Enum\Payment\PaymentProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

class PurchaseRequestDto
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

    #[Assert\NotBlank]
    #[Assert\Choice(choices: [PaymentProcessor::PAYPAL, PaymentProcessor::STRIPE], message: "Need appropriate payment processor")]
    public ?string $paymentProcessor;

    /** @var float amount of money for the purchase */
    #[Assert\NotBlank]
    #[Assert\Positive(message: "Amount must be a positive number")]
    public ?float $amount;

    public function __construct(Request $request)
    {
        $data = json_decode($request->getContent());

        $this->product = $data->product ?? null;
        $this->taxNumber = $data->taxNumber ?? null;
        $this->couponCode = $data->couponCode ?? null;
        $this->paymentProcessor = $data->paymentProcessor ?? null;
        $this->amount = $data->amount ?? null;
    }
}