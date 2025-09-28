<?php

namespace App\Controller\Api;

use App\Controller\Exceptions\PaymentFailedException;
use App\Controller\Exceptions\ValidationException;
use App\Controller\Traits\ApiJsonResponseTrait;
use App\Dto\CalculatePriceRequestDto;
use App\Dto\PurchaseRequestDto;
use App\Enum\Payment\CountryTaxNumberPattern;
use App\Enum\Payment\CountryTaxPercent;
use App\Enum\Payment\PaymentProcessor;
use App\Repository\CouponRepository;
use App\Service\OrderService;
use App\Service\PaymentService;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class PublicController extends AbstractController
{
    use ApiJsonResponseTrait;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly ProductService $productService,
        private readonly CouponRepository $couponRepository,
        private readonly ValidatorInterface $validator
    ) {}

    #[Route('/calculate-price', name: 'calculate_price', methods: ['POST'])]
    public function calculatePrice(Request $request): JsonResponse
    {
        $data = new CalculatePriceRequestDto($request);
        $errors = $this->validator->validate($data);

        if (count($errors) > 0) {
            return $this->fail(new ValidationException($errors));
        }

        if (!$product = $this->productService->get($data->product)) {
            return $this->fail(new NotFoundHttpException("Product not found"));
        }

        if (!$country = CountryTaxNumberPattern::getCountry($data->taxNumber)) {
            return $this->fail(new NotFoundHttpException("Inappropriate tax number"));
        }

        if (
            (!$coupon = $this->couponRepository->findByCode($data->couponCode)) //coupon was not found
            || !$coupon->isValid() // coupon is not valid
            || $product->getCoupon()?->getCode() !== $coupon->getCode() // coupon for different product
        ) {
            $coupon = null;
        }

        $price = $this->paymentService->calculatePrice(
            $product->getPrice(),
            CountryTaxPercent::get($country),
            $coupon
        );

        return $this->ok([
            'product' => $product->getName(),
            'price' => $price,
            'coupon' => $coupon ? $coupon->getCode() : "Coupon is not valid",
            'tax' => CountryTaxPercent::get($country)->value . '%'
        ]);
    }

    #[Route('/purchase', name: 'purchase', methods: ['POST'])]
    public function purchase(Request $request): JsonResponse
    {
        $data = new PurchaseRequestDto($request);
        // Params validation
        $errors = $this->validator->validate($data);

        if (count($errors) > 0) {
            return $this->fail(new ValidationException($errors));
        }

        // Check product
        if (!$product = $this->productService->get($data->product)) {
            return $this->fail(new NotFoundHttpException("Product not found"));
        }

        // Check country tax number
        if (!$country = CountryTaxNumberPattern::getCountry($data->taxNumber)) {
            return $this->fail(new NotFoundHttpException("Inappropriate tax number"));
        }

        // Check coupon
        if (
            (!$coupon = $this->couponRepository->findByCode($data->couponCode))
            || !$coupon->isValid()
            || $product->getCoupon()?->getCode() !== $coupon->getCode()
        ) {
            $coupon = null;
        }

        // Price calculation
        $calculatedPrice = $this->paymentService->calculatePrice(
            $product->getPrice(),
            CountryTaxPercent::get($country),
            $coupon
        );

        if ($data->amount < $calculatedPrice) {
            return $this->fail(new PaymentFailedException("Insufficient funds"));
        }

        $processed = $this->paymentService->processPayment(
            PaymentProcessor::from($data->paymentProcessor),
            $data->amount,
        );

        if ($processed) {
            return $this->ok(['message' => 'Payment processed successfully!']);
        }
        else {
            return $this->fail(new PaymentFailedException("Payment failed!"));
        }
    }
}