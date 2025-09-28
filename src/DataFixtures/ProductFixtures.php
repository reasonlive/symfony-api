<?php

namespace App\DataFixtures;

use App\Entity\Coupon;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $products = [
            [
                'name' => 'iPhone 15 Pro',
                'description' => 'Флагманский смартфон Apple с процессором A17 Pro и камерой 48 МП',
                'price' => 1199.99,
                'stock' => 50,
                'isActive' => true,
                'couponReference' => 'coupon-0', // WELCOME10
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'description' => 'Мощный Android-смартфон с экраном AMOLED и продвинутой камерой',
                'price' => 899.99,
                'stock' => 75,
                'isActive' => true,
                'couponReference' => 'coupon-1', // FIXED15
            ],
            [
                'name' => 'MacBook Air M2',
                'description' => 'Легкий и мощный ноутбук с чипом Apple M2 и дисплеем Retina',
                'price' => 1299.00,
                'stock' => 25,
                'isActive' => true,
                'couponReference' => 'coupon-2', // SUMMER25
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'description' => 'Беспроводные наушники с шумоподавлением и премиальным звуком',
                'price' => 349.99,
                'stock' => 100,
                'isActive' => true,
                'couponReference' => 'coupon-3', // FREESHIP
            ],
            [
                'name' => 'Apple Watch Series 9',
                'description' => 'Умные часы с функцией измерения ЭКГ и Always-On дисплеем',
                'price' => 399.00,
                'stock' => 0, // Нет в наличии
                'isActive' => false, // Неактивный товар
                'couponReference' => null, // Без купона
            ],
            [
                'name' => 'Headphones TWS900',
                'description' => 'Дешевые наушники TWS',
                'price' => 20.00,
                'stock' => 100,
                'isActive' => true,
                'couponReference' => null,
            ],
            [
                'name' => 'Plastic phone case Samsung+',
                'description' => 'Пластиковый чехол для телефона Samsung',
                'price' => 10.00,
                'stock' => 100,
                'isActive' => true,
                'couponReference' => null,
            ]
        ];

        foreach ($products as $productData) {
            $product = new Product();
            $product->setName($productData['name']);
            $product->setDescription($productData['description']);
            $product->setPrice($productData['price']);
            $product->setStock($productData['stock']);
            $product->setActive($productData['isActive']);

            // Устанавливаем купон, если указан
            if (
                $productData['couponReference']
                && $this->hasReference($productData['couponReference'], Coupon::class)
            ) {
                $coupon = $this->getReference($productData['couponReference'], Coupon::class);
                $product->setCoupon($coupon);
            }

            $manager->persist($product);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CouponFixtures::class,
        ];
    }
}