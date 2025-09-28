<?php

namespace App\DataFixtures;

use App\Entity\Coupon;
use App\Enum\Coupon\Status;
use App\Enum\Coupon\Type;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CouponFixtures extends Fixture
{
    public const COUPON_REFERENCE_PREFIX = 'coupon-';

    public function load(ObjectManager $manager): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'type' => Type::PERCENTAGE,
                'value' => 10.00,
                'status' => Status::ACTIVE,
                'description' => 'Скидка 10% для новых клиентов',
                'usageLimit' => 100,
                'timesUsed' => 25,
                'validFrom' => new \DateTimeImmutable('-1 month'),
                'validTo' => new \DateTimeImmutable('+2 months'),
            ],
            [
                'code' => 'FIXED15',
                'type' => Type::FIXED,
                'value' => 15.00,
                'status' => Status::ACTIVE,
                'description' => 'Фиксированная скидка 15€',
                'usageLimit' => 50,
                'timesUsed' => 12,
                'validFrom' => new \DateTimeImmutable('-1 week'),
                'validTo' => new \DateTimeImmutable('+1 month'),
            ],
            [
                'code' => 'SUMMER25',
                'type' => Type::PERCENTAGE,
                'value' => 25.00,
                'status' => Status::ACTIVE,
                'description' => 'Летняя скидка 25%',
                'usageLimit' => 200,
                'timesUsed' => 89,
                'validFrom' => new \DateTimeImmutable('-15 days'),
                'validTo' => new \DateTimeImmutable('+45 days'),
            ],
            [
                'code' => 'FREESHIP',
                'type' => Type::FIXED,
                'value' => 5.99,
                'status' => Status::ACTIVE,
                'description' => 'Бесплатная доставка',
                'usageLimit' => null, // Без лимита
                'timesUsed' => 156,
                'validFrom' => null, // Действует всегда
                'validTo' => null,
            ],
            [
                'code' => 'EXPIRED99',
                'type' => Type::PERCENTAGE,
                'value' => 50.00,
                'status' => Status::INACTIVE,
                'description' => 'Просроченный купон',
                'usageLimit' => 100,
                'timesUsed' => 100,
                'validFrom' => new \DateTimeImmutable('-6 months'),
                'validTo' => new \DateTimeImmutable('-1 month'),
            ],
        ];

        foreach ($coupons as $index => $couponData) {
            $coupon = new Coupon();
            $coupon->setCode($couponData['code']);
            $coupon->setType($couponData['type']);
            $coupon->setValue($couponData['value']);
            $coupon->setStatus($couponData['status']);
            $coupon->setDescription($couponData['description']);
            $coupon->setUsageLimit($couponData['usageLimit']);
            $coupon->setTimesUsed($couponData['timesUsed']);
            $coupon->setValidFrom($couponData['validFrom']);
            $coupon->setValidTo($couponData['validTo']);

            $manager->persist($coupon);
            $this->addReference(self::COUPON_REFERENCE_PREFIX . $index, $coupon);
        }

        $manager->flush();
    }
}