<?php

namespace App\Tests\Service;

use App\Entity\Coupon;
use App\Enum\Payment\CountryTaxPercent;
use App\Enum\Payment\PaymentProcessor;
use App\Service\PaymentService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Systemeio\TestForCandidates\PaymentProcessor\PaypalPaymentProcessor;
use Systemeio\TestForCandidates\PaymentProcessor\StripePaymentProcessor;

class PaymentServiceTest extends TestCase
{
    private PaypalPaymentProcessor $paypalProcessor;
    private StripePaymentProcessor $stripeProcessor;
    private LoggerInterface $logger;
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        $this->paypalProcessor = $this->createMock(PaypalPaymentProcessor::class);
        $this->stripeProcessor = $this->createMock(StripePaymentProcessor::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->paymentService = new PaymentService(
            $this->paypalProcessor,
            $this->stripeProcessor,
            $this->logger
        );
    }

    public function testProcessPaymentWithStripeSuccess(): void
    {
        $amount = 150.0;

        $this->stripeProcessor
            ->expects($this->once())
            ->method('processPayment')
            ->with($amount)
            ->willReturn(true);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $result = $this->paymentService->processPayment(PaymentProcessor::STRIPE, $amount);

        $this->assertTrue($result);
    }

    public function testProcessPaymentWithStripeFailure(): void
    {
        $amount = 150.0;

        $this->stripeProcessor
            ->expects($this->once())
            ->method('processPayment')
            ->with($amount)
            ->willReturn(false);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $result = $this->paymentService->processPayment(PaymentProcessor::STRIPE, $amount);

        $this->assertFalse($result);
    }

    public function testProcessPaymentWithStripeLowAmount(): void
    {
        $amount = 50.0; // Amount less than 100 should fail for Stripe

        $this->stripeProcessor
            ->expects($this->once())
            ->method('processPayment')
            ->with($amount)
            ->willReturn(false);

        $result = $this->paymentService->processPayment(PaymentProcessor::STRIPE, $amount);

        $this->assertFalse($result);
    }

    public function testProcessPaymentWithPaypalSuccess(): void
    {
        $amount = 500.0;

        $this->paypalProcessor
            ->expects($this->once())
            ->method('pay')
            ->with(500); // Paypal expects integer amount

        $this->logger
            ->expects($this->never())
            ->method('error');

        $result = $this->paymentService->processPayment(PaymentProcessor::PAYPAL, $amount);

        $this->assertTrue($result);
    }

    public function testProcessPaymentWithPaypalException(): void
    {
        $amount = 200000.0; // This should cause Paypal to throw exception

        $exception = new \Exception('[#14271] Transaction failed: Too high price');

        $this->paypalProcessor
            ->expects($this->once())
            ->method('pay')
            ->with(200000)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Payment error: [#14271] Transaction failed: Too high price');

        $result = $this->paymentService->processPayment(PaymentProcessor::PAYPAL, $amount);

        $this->assertFalse($result);
    }

    public function testProcessPaymentWithPaypalAmountConversion(): void
    {
        $amount = 123.45;

        $this->paypalProcessor
            ->expects($this->once())
            ->method('pay')
            ->with(123); // Paypal should receive integer part

        $result = $this->paymentService->processPayment(PaymentProcessor::PAYPAL, $amount);

        $this->assertTrue($result);
    }

    public function testCalculatePriceWithoutCoupon(): void
    {
        $productPrice = 100.0;
        $taxPercent = CountryTaxPercent::GREECE;
        $expectedPrice = 124.0; // 100 + 24% tax

        $result = $this->paymentService->calculatePrice($productPrice, $taxPercent);

        $this->assertEquals($expectedPrice, $result);
    }

    public function testCalculatePriceWithFixedCoupon(): void
    {
        $productPrice = 100.0;
        $taxPercent = CountryTaxPercent::GREECE;

        $coupon = $this->createMock(Coupon::class);
        $coupon->method('isPercentage')->willReturn(false);
        $coupon->method('getValue')->willReturn(20.0);

        $expectedPrice = 104.0; // 100 + 24% tax - 20 fixed discount = 124 - 20 = 104

        $result = $this->paymentService->calculatePrice($productPrice, $taxPercent, $coupon);

        $this->assertEquals($expectedPrice, $result);
    }

    public function testCalculatePriceWithPercentageCoupon(): void
    {
        $productPrice = 100.0;
        $taxPercent = CountryTaxPercent::GREECE;

        $coupon = $this->createMock(Coupon::class);
        $coupon->method('isPercentage')->willReturn(true);
        $coupon->method('getValue')->willReturn(6.0);

        $expectedPrice = 116.56; // 100 + 24% tax = 124, then 6% discount = 124 * 0.94 = 116.56

        $result = $this->paymentService->calculatePrice($productPrice, $taxPercent, $coupon);

        $this->assertEquals($expectedPrice, $result);
    }

    public function testCalculatePriceWithPercentageCouponAndHighDiscount(): void
    {
        $productPrice = 100.0;
        $taxPercent = CountryTaxPercent::GERMANY;

        $coupon = $this->createMock(Coupon::class);
        $coupon->method('isPercentage')->willReturn(true);
        $coupon->method('getValue')->willReturn(50.0);

        $expectedPrice = 59.5; // 100 + 19% tax = 119, then 50% discount = 119 * 0.5 = 59.5

        $result = $this->paymentService->calculatePrice($productPrice, $taxPercent, $coupon);

        $this->assertEquals($expectedPrice, $result);
    }

    public function testCalculatePriceWithFixedCouponHigherThanPrice(): void
    {
        $productPrice = 50.0;
        $taxPercent = CountryTaxPercent::FRANCE;

        $coupon = $this->createMock(Coupon::class);
        $coupon->method('isPercentage')->willReturn(false);
        $coupon->method('getValue')->willReturn(100.0);

        $expectedPrice = 0.0; // 50 + 20% tax = 60, but discount 100 should cap at 60

        $result = $this->paymentService->calculatePrice($productPrice, $taxPercent, $coupon);

        $this->assertEquals($expectedPrice, $result);
    }

    public function testCalculatePriceWithNullCoupon(): void
    {
        $productPrice = 100.0;
        $taxPercent = CountryTaxPercent::ITALY;
        $expectedPrice = 122.0; // 100 + 22% tax

        $result = $this->paymentService->calculatePrice($productPrice, $taxPercent, null);

        $this->assertEquals($expectedPrice, $result);
    }

    public function testCalculatePriceWithZeroPrice(): void
    {
        $productPrice = 0.0;
        $taxPercent = CountryTaxPercent::GERMANY;

        $coupon = $this->createMock(Coupon::class);
        $coupon->method('isPercentage')->willReturn(true);
        $coupon->method('getValue')->willReturn(10.0);

        $expectedPrice = 0.0;

        $result = $this->paymentService->calculatePrice($productPrice, $taxPercent, $coupon);

        $this->assertEquals($expectedPrice, $result);
    }

    public function testCalculatePriceRounding(): void
    {
        $productPrice = 99.99;
        $taxPercent = CountryTaxPercent::GREECE;

        $coupon = $this->createMock(Coupon::class);
        $coupon->method('isPercentage')->willReturn(true);
        $coupon->method('getValue')->willReturn(7.5);

        // 99.99 + 24% = 123.9876, then 7.5% discount = 114.688383, rounded to 114.69
        $expectedPrice = 114.69;

        $result = $this->paymentService->calculatePrice($productPrice, $taxPercent, $coupon);

        $this->assertEquals($expectedPrice, $result);
    }

    public function testCalculatePriceAllCountries(): void
    {
        $productPrice = 100.0;

        $testCases = [
            CountryTaxPercent::GERMANY->name => 119.0,  // 19%
            CountryTaxPercent::ITALY->name => 122.0,    // 22%
            CountryTaxPercent::GREECE->name => 124.0,   // 24%
            CountryTaxPercent::FRANCE->name => 120.0,   // 20%
        ];

        foreach ($testCases as $country => $expectedPrice) {
            $result = $this->paymentService->calculatePrice($productPrice, CountryTaxPercent::get($country));
            $this->assertEquals($expectedPrice, $result, "Failed for country: {$country}");
        }
    }

    public function testCalculatePriceWithComplexDiscountScenario(): void
    {
        // Тестовый сценарий из комментария: Iphone для Греции с купоном 6%
        $productPrice = 100.0; // базовая цена
        $taxPercent = CountryTaxPercent::GREECE; // 24% налог
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('isPercentage')->willReturn(true);
        $coupon->method('getValue')->willReturn(6.0);

        $expectedPrice = 116.56; // 100 + 24% = 124, затем 6% скидка = 116.56

        $result = $this->paymentService->calculatePrice($productPrice, $taxPercent, $coupon);

        $this->assertEquals($expectedPrice, $result);
    }

    public function testProcessPaymentLogsDifferentExceptions(): void
    {
        $amount = 100.0;
        $exception = new \RuntimeException('Network error');

        $this->paypalProcessor
            ->expects($this->once())
            ->method('pay')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Payment error: Network error');

        $result = $this->paymentService->processPayment(PaymentProcessor::PAYPAL, $amount);

        $this->assertFalse($result);
    }
}