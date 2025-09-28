<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Coupon;
use App\Enum\Payment\CountryTaxPercent;
use App\Enum\Payment\PaymentProcessor;
use Psr\Log\LoggerInterface;
use Systemeio\TestForCandidates\PaymentProcessor\PaypalPaymentProcessor;
use Systemeio\TestForCandidates\PaymentProcessor\StripePaymentProcessor;

class PaymentService
{
    public function __construct(
        private PaypalPaymentProcessor $paypal,
        private StripePaymentProcessor $stripe,
        private LoggerInterface $logger
    )
    {
    }

    public function processPayment(PaymentProcessor $paymentProcessor, float $amount): bool
    {
        try {
            switch ($paymentProcessor) {
                case PaymentProcessor::STRIPE:
                    return $this->stripe->processPayment($amount);
                case PaymentProcessor::PAYPAL:
                    $this->paypal->pay((int)$amount);
                    return true;
                default:
                    return false;
            }
        }
        catch (\Throwable $e) {
            $this->logger->error("Payment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Например, цена Iphone для покупателя из Греции составит 124 евро (100 евро + налог 24%). Если у покупателя есть купон на 6% скидку, то цена будет 116.56
     * @param float $productPrice Цена продукта
     * @param CountryTaxPercent $percent Процент налога на основании налогового номера страны
     * @param Coupon|null $coupon Скидка по товару
     * @return float
     */
    public function calculatePrice(float $productPrice, CountryTaxPercent $percent, ?Coupon $coupon = null): float
    {
        $discount = 0;
        if ($coupon) {
            $discount = $coupon->isPercentage()
                ? $productPrice * $coupon->getValue() / 100
                : $coupon->getValue();
        }

        return $productPrice + ($productPrice * $percent->value / 100) - $discount;
    }
}