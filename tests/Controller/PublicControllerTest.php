<?php

namespace App\Tests\Controller;

use App\Controller\Api\PublicController;
use App\Dto\CalculatePriceRequestDto;
use App\Dto\PurchaseRequestDto;
use App\Entity\Coupon;
use App\Entity\Product;
use App\Enum\Payment\CountryTaxPercent;
use App\Enum\Payment\PaymentProcessor;
use App\Controller\Exceptions\PaymentFailedException;
use App\Controller\Exceptions\ValidationException;
use App\Repository\CouponRepository;
use App\Service\PaymentService;
use App\Service\ProductService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PublicControllerTest extends TestCase
{
    private PaymentService $paymentService;
    private ProductService $productService;
    private CouponRepository $couponRepository;
    private ValidatorInterface $validator;
    private PublicController $controller;

    protected function setUp(): void
    {
        $this->paymentService = $this->createMock(PaymentService::class);
        $this->productService = $this->createMock(ProductService::class);
        $this->couponRepository = $this->createMock(CouponRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->controller = new PublicController(
            $this->paymentService,
            $this->productService,
            $this->couponRepository,
            $this->validator
        );
    }

    public function testCalculatePriceSuccess(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'DE123456789',
            'couponCode' => 'SUMMER10'
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $product = new Product();
        $product->setId(1);
        $product->setName('iPhone');
        $product->setPrice(100.0);

        $coupon = new Coupon();
        $coupon->setCode('SUMMER10');
        $coupon->setType(\App\Enum\Coupon\Type::PERCENTAGE);
        $coupon->setValue(10.0);

        $product->setCoupon($coupon);

        // Mock validation - no errors
        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $this->couponRepository
            ->expects($this->once())
            ->method('findByCode')
            ->with('SUMMER10')
            ->willReturn($coupon);

        $this->paymentService
            ->expects($this->once())
            ->method('calculatePrice')
            ->with(100.0, CountryTaxPercent::GERMANY, $coupon)
            ->willReturn(109.0); // 100 + 19% - 10% = 109

        $response = $this->controller->calculatePrice($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('iPhone', $responseData['data']['product']);
        $this->assertEquals(109.0, $responseData['data']['price']);
        $this->assertEquals('SUMMER10', $responseData['data']['coupon']);
        $this->assertEquals('19%', $responseData['data']['tax']);
    }

    public function testCalculatePriceValidationFailed(): void
    {
        $requestData = [
            'product' => null, // Invalid - missing product
            'taxNumber' => null, // Invalid - missing tax number
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Product ID must be a positive number', '', [], '', 'product', null),
            new ConstraintViolation('This value should not be blank', '', [], '', 'taxNumber', null),
        ]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $response = $this->controller->calculatePrice($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Validation failed', $responseData['error']);
        $this->assertIsArray($responseData['details']);
        $this->assertCount(2, $responseData['details']);
    }

    public function testCalculatePriceProductNotFound(): void
    {
        $requestData = [
            'product' => 999,
            'taxNumber' => 'DE123456789',
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn(null);

        $response = $this->controller->calculatePrice($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Product not found', $responseData['error']);
    }

    public function testCalculatePriceInvalidTaxNumber(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'INVALID123', // Invalid tax number
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $product = new Product();
        $product->setId(1);
        $product->setName('iPhone');
        $product->setPrice(100.0);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $response = $this->controller->calculatePrice($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Inappropriate tax number', $responseData['error']);
    }

    public function testCalculatePriceWithInvalidCoupon(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'DE123456789',
            'couponCode' => 'INVALID'
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $product = new Product();
        $product->setId(1);
        $product->setName('iPhone');
        $product->setPrice(100.0);

        $validCoupon = new Coupon();
        $validCoupon->setCode('VALID123');
        $product->setCoupon($validCoupon); // Product has different coupon

        $invalidCoupon = new Coupon();
        $invalidCoupon->setCode('INVALID');
        $invalidCoupon->setStatus(\App\Enum\Coupon\Status::INACTIVE); // Inactive coupon

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $this->couponRepository
            ->expects($this->once())
            ->method('findByCode')
            ->with('INVALID')
            ->willReturn($invalidCoupon);

        $this->paymentService
            ->expects($this->once())
            ->method('calculatePrice')
            ->with(100.0, CountryTaxPercent::GERMANY, null) // Should pass null for coupon
            ->willReturn(119.0);

        $response = $this->controller->calculatePrice($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Coupon is not valid', $responseData['data']['coupon']);
    }

    public function testCalculatePriceWithDifferentCountryTaxNumbers(): void
    {
        $testCases = [
            ['taxNumber' => 'DE123456789', 'country' => 'GERMANY', 'taxPercent' => CountryTaxPercent::GERMANY],
            ['taxNumber' => 'IT12345678901', 'country' => 'ITALY', 'taxPercent' => CountryTaxPercent::ITALY],
            ['taxNumber' => 'GR123456789', 'country' => 'GREECE', 'taxPercent' => CountryTaxPercent::GREECE],
            ['taxNumber' => 'FRAB123456789', 'country' => 'FRANCE', 'taxPercent' => CountryTaxPercent::FRANCE],
        ];

        $product = new Product();
        $product->setId(1);
        $product->setName('iPhone');
        $product->setPrice(100.0);

        // Устанавливаем ожидания ДО цикла
        $this->validator
            ->expects($this->exactly(4))  // ← Ожидаем 4 вызова
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->exactly(4))
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $this->couponRepository
            ->expects($this->exactly(4))
            ->method('findByCode')
            ->with(null)
            ->willReturn(null);

        $this->paymentService
            ->expects($this->exactly(4))
            ->method('calculatePrice')
            ->willReturnCallback(function ($price, $taxPercent, $coupon) {
                return $price + ($price * $taxPercent->value / 100);
            });

        // Теперь выполняем цикл
        foreach ($testCases as $testCase) {
            $requestData = [
                'product' => 1,
                'taxNumber' => $testCase['taxNumber'],
            ];

            $request = new Request([], [], [], [], [], [], json_encode($requestData));

            $response = $this->controller->calculatePrice($request);

            $this->assertInstanceOf(JsonResponse::class, $response);
            $responseData = json_decode($response->getContent(), true);

            $this->assertTrue($responseData['success']);
            $this->assertEquals($testCase['taxPercent']->value . '%', $responseData['data']['tax']);
        }
    }

    public function testPurchaseSuccess(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'DE123456789',
            'couponCode' => 'SUMMER10',
            'paymentProcessor' => 'paypal',
            'amount' => 150.0
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $product = new Product();
        $product->setId(1);
        $product->setName('iPhone');
        $product->setPrice(100.0);

        $coupon = new Coupon();
        $coupon->setCode('SUMMER10');
        $coupon->setType(\App\Enum\Coupon\Type::PERCENTAGE);
        $coupon->setValue(10.0);

        $product->setCoupon($coupon);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $this->couponRepository
            ->expects($this->once())
            ->method('findByCode')
            ->with('SUMMER10')
            ->willReturn($coupon);

        $this->paymentService
            ->expects($this->once())
            ->method('calculatePrice')
            ->with(100.0, CountryTaxPercent::GERMANY, $coupon)
            ->willReturn(109.0);

        $this->paymentService
            ->expects($this->once())
            ->method('processPayment')
            ->with(PaymentProcessor::PAYPAL, 150.0)
            ->willReturn(true);

        $response = $this->controller->purchase($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Payment processed successfully!', $responseData['data']['message']);
    }

    public function testPurchaseInsufficientFunds(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'DE123456789',
            'paymentProcessor' => 'paypal',
            'amount' => 50.0 // Less than calculated price
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $dto = new PurchaseRequestDto($request);
        $this->assertNull($dto->couponCode);

        $product = new Product();
        $product->setId(1);
        $product->setName('iPhone');
        $product->setPrice(100.0);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $this->couponRepository
            ->expects($this->once())
            ->method('findByCode')
            ->with($this->anything())
            ->willReturn(null);

        $this->paymentService
            ->expects($this->once())
            ->method('calculatePrice')
            ->with(100.0, CountryTaxPercent::GERMANY, null)
            ->willReturn(119.0); // Calculated price is 119

        // Amount is 50, which is less than 119 - should fail

        $response = $this->controller->purchase($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Insufficient funds', $responseData['error']);
    }

    public function testPurchasePaymentFailed(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'DE123456789',
            'paymentProcessor' => 'stripe',
            'amount' => 150.0
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $dto = new PurchaseRequestDto($request);
        $this->assertNull($dto->couponCode);

        $product = new Product();
        $product->setId(1);
        $product->setName('iPhone');
        $product->setPrice(100.0);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $this->couponRepository
            ->expects($this->once())
            ->method('findByCode')
            ->with($dto->couponCode)
            ->willReturn(null);

        $this->paymentService
            ->expects($this->once())
            ->method('calculatePrice')
            ->with(100.0, CountryTaxPercent::GERMANY, null)
            ->willReturn(119.0);

        $this->paymentService
            ->expects($this->once())
            ->method('processPayment')
            ->with(PaymentProcessor::STRIPE, 150.0)
            ->willReturn(false);

        $response = $this->controller->purchase($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Payment failed!', $responseData['error']);
    }

    public function testPurchaseWithStripeProcessor(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'IT12345678901',
            'paymentProcessor' => 'stripe',
            'amount' => 200.0
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $dto = new PurchaseRequestDto($request);
        $this->assertNull($dto->couponCode);

        $product = new Product();
        $product->setId(1);
        $product->setName('iPhone');
        $product->setPrice(100.0);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $this->couponRepository
            ->expects($this->once())
            ->method('findByCode')
            ->with($dto->couponCode)
            ->willReturn(null);

        $this->paymentService
            ->expects($this->once())
            ->method('calculatePrice')
            ->with(100.0, CountryTaxPercent::ITALY, null)
            ->willReturn(122.0);

        $this->paymentService
            ->expects($this->once())
            ->method('processPayment')
            ->with(PaymentProcessor::STRIPE, 200.0)
            ->willReturn(true);

        $response = $this->controller->purchase($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
    }

    public function testPurchaseValidationFailed(): void
    {
        $requestData = [
            'product' => null,
            'taxNumber' => null,
            'paymentProcessor' => 'invalid',
            'amount' => -50.0
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Product ID must be a positive number', '', [], '', 'product', null),
            new ConstraintViolation('This value should not be blank', '', [], '', 'taxNumber', null),
            new ConstraintViolation('Need appropriate payment processor', '', [], '', 'paymentProcessor', 'invalid'),
            new ConstraintViolation('Amount must be a positive number', '', [], '', 'amount', -50.0),
        ]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $response = $this->controller->purchase($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Validation failed', $responseData['error']);
        $this->assertCount(4, $responseData['details']);
    }

    public function testPurchaseWithCouponForDifferentProduct(): void
    {
        $requestData = [
            'product' => 1,
            'taxNumber' => 'DE123456789',
            'couponCode' => 'OTHERPRODUCT',
            'paymentProcessor' => 'paypal',
            'amount' => 150.0
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $product = new Product();
        $product->setId(1);
        $product->setName('iPhone');
        $product->setPrice(100.0);

        // Product has a different coupon attached
        $productCoupon = new Coupon();
        $productCoupon->setCode('IPHONE10');
        $product->setCoupon($productCoupon);

        // Found coupon but for different product
        $otherCoupon = new Coupon();
        $otherCoupon->setCode('OTHERPRODUCT');
        $otherCoupon->setStatus(\App\Enum\Coupon\Status::ACTIVE);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productService
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($product);

        $this->couponRepository
            ->expects($this->once())
            ->method('findByCode')
            ->with('OTHERPRODUCT')
            ->willReturn($otherCoupon);

        // Should calculate price without coupon
        $this->paymentService
            ->expects($this->once())
            ->method('calculatePrice')
            ->with(100.0, CountryTaxPercent::GERMANY, null)
            ->willReturn(119.0);

        $this->paymentService
            ->expects($this->once())
            ->method('processPayment')
            ->with(PaymentProcessor::PAYPAL, 150.0)
            ->willReturn(true);

        $response = $this->controller->purchase($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
    }
}