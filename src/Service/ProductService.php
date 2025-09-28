<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;
class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    public function get(int $id): ?Product
    {
        return $this->productRepository->find($id);
    }

    public function createProduct(array $data): Product
    {
        $product = new Product();
        $product->setName($data['name']);
        $product->setPrice((float) $data['price']);
        $product->setStock((int) $data['stock']);

        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException((string) $errors);
        }

        $this->productRepository->save($product, true);

        return $product;
    }

    public function updateProduct(Product $product, array $data): Product
    {
        if (isset($data['name'])) {
            $product->setName($data['name']);
        }

        if (isset($data['description'])) {
            $product->setDescription($data['description']);
        }

        if (isset($data['price'])) {
            $product->setPrice((float) $data['price']);
        }

        if (isset($data['stock'])) {
            $product->setStock((int) $data['stock']);
        }

        if (isset($data['isActive'])) {
            $product->setActive((bool) $data['isActive']);
        }

        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException((string) $errors);
        }

        $this->productRepository->save($product, true);

        return $product;
    }

    public function decreaseStock(Product $product, int $quantity): void
    {
        if ($product->getStock() < $quantity) {
            throw new \InvalidArgumentException(
                sprintf('Not enough stock. Available: %d, requested: %d', $product->getStock(), $quantity)
            );
        }

        $this->productRepository->decreaseStock($product->getId(), $quantity);
    }

    public function getLowStockProducts(int $threshold = 10): array
    {
        return $this->productRepository->findProductsWithLowStock($threshold);
    }

    public function searchProducts(string $searchTerm, int $page = 1): array
    {
        return $this->productRepository->searchProducts($searchTerm, $page);
    }

    public function deleteProduct(Product $product): void
    {
        $this->productRepository->remove($product, true);
    }
}