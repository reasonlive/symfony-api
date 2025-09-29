<?php

namespace App\Tests\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductServiceTest extends TestCase
{
    private ProductRepository $productRepository;
    private ValidatorInterface $validator;
    private ProductService $productService;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->productService = new ProductService(
            $this->productRepository,
            $this->validator
        );
    }

    public function testGetProduct(): void
    {
        $product = new Product();
        $product->setName('Test Product');

        $this->productRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($product);

        $result = $this->productService->get(1);

        $this->assertSame($product, $result);
    }

    public function testGetProductNotFound(): void
    {
        $this->productRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->productService->get(999);

        $this->assertNull($result);
    }

    public function testCreateProductSuccess(): void
    {
        $productData = [
            'name' => 'New Product',
            'price' => 99.99,
            'stock' => 50
        ];

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Product::class), true);

        $product = $this->productService->createProduct($productData);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('New Product', $product->getName());
        $this->assertEquals(99.99, $product->getPrice());
        $this->assertEquals(50, $product->getStock());
    }

    public function testCreateProductValidationFailed(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $productData = [
            'name' => '', // Invalid empty name
            'price' => -10, // Invalid negative price
            'stock' => -5 // Invalid negative stock
        ];

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Name cannot be blank', '', [], '', 'name', ''),
            new ConstraintViolation('Price must be positive', '', [], '', 'price', -10),
        ]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $this->productRepository
            ->expects($this->never())
            ->method('save');

        $this->productService->createProduct($productData);
    }

    public function testUpdateProductSuccess(): void
    {
        $existingProduct = new Product();
        $existingProduct->setName('Old Name');
        $existingProduct->setPrice(50.0);
        $existingProduct->setStock(100);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'price' => 75.0,
            'stock' => 80,
            'isActive' => false
        ];

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Product::class), true);

        $result = $this->productService->updateProduct($existingProduct, $updateData);

        $this->assertSame($existingProduct, $result);
        $this->assertEquals('Updated Name', $result->getName());
        $this->assertEquals('Updated Description', $result->getDescription());
        $this->assertEquals(75.0, $result->getPrice());
        $this->assertEquals(80, $result->getStock());
        $this->assertFalse($result->isActive());
    }

    public function testUpdateProductPartialData(): void
    {
        $existingProduct = new Product();
        $existingProduct->setName('Original Name');
        $existingProduct->setPrice(50.0);
        $existingProduct->setStock(100);

        $updateData = [
            'name' => 'Updated Name',
            // Only updating name, other fields should remain unchanged
        ];

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Product::class), true);

        $result = $this->productService->updateProduct($existingProduct, $updateData);

        $this->assertEquals('Updated Name', $result->getName());
        $this->assertEquals(50.0, $result->getPrice()); // Should remain unchanged
        $this->assertEquals(100, $result->getStock()); // Should remain unchanged
    }

    public function testUpdateProductValidationFailed(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $existingProduct = new Product();
        $existingProduct->setName('Original Name');

        $updateData = [
            'name' => '', // Invalid empty name
        ];

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Name cannot be blank', '', [], '', 'name', ''),
        ]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $this->productRepository
            ->expects($this->never())
            ->method('save');

        $this->productService->updateProduct($existingProduct, $updateData);
    }

    public function testDecreaseStockSuccess(): void
    {
        $product = new Product();
        $product->setId(1);
        $product->setStock(100);

        $this->productRepository
            ->expects($this->once())
            ->method('decreaseStock')
            ->with(1, 25);

        $this->productService->decreaseStock($product, 25);

        // Stock should remain unchanged in the entity since we're using repository method
        $this->assertEquals(100, $product->getStock());
    }

    public function testDecreaseStockInsufficientStock(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not enough stock. Available: 10, requested: 25');

        $product = new Product();
        $product->setStock(10);

        $this->productRepository
            ->expects($this->never())
            ->method('decreaseStock');

        $this->productService->decreaseStock($product, 25);
    }

    public function testGetLowStockProducts(): void
    {
        $lowStockProducts = [
            new Product(),
            new Product(),
        ];

        $this->productRepository
            ->expects($this->once())
            ->method('findProductsWithLowStock')
            ->with(10)
            ->willReturn($lowStockProducts);

        $result = $this->productService->getLowStockProducts(10);

        $this->assertSame($lowStockProducts, $result);
    }

    public function testGetLowStockProductsWithCustomThreshold(): void
    {
        $lowStockProducts = [new Product()];

        $this->productRepository
            ->expects($this->once())
            ->method('findProductsWithLowStock')
            ->with(5)
            ->willReturn($lowStockProducts);

        $result = $this->productService->getLowStockProducts(5);

        $this->assertSame($lowStockProducts, $result);
    }

    public function testSearchProducts(): void
    {
        $searchResults = [new Product(), new Product()];

        $this->productRepository
            ->expects($this->once())
            ->method('searchProducts')
            ->with('test query', 2)
            ->willReturn($searchResults);

        $result = $this->productService->searchProducts('test query', 2);

        $this->assertSame($searchResults, $result);
    }

    public function testSearchProductsDefaultPage(): void
    {
        $searchResults = [new Product()];

        $this->productRepository
            ->expects($this->once())
            ->method('searchProducts')
            ->with('test', 1)
            ->willReturn($searchResults);

        $result = $this->productService->searchProducts('test');

        $this->assertSame($searchResults, $result);
    }

    public function testDeleteProduct(): void
    {
        $product = new Product();

        $this->productRepository
            ->expects($this->once())
            ->method('remove')
            ->with($product, true);

        $this->productService->deleteProduct($product);

        // No assertion needed, just verifying the method is called
    }

    public function testCreateProductWithMinimalData(): void
    {
        $productData = [
            'name' => 'Minimal Product',
            'price' => 0.0,
            'stock' => 0
        ];

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->productRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Product::class), true);

        $product = $this->productService->createProduct($productData);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Minimal Product', $product->getName());
        $this->assertEquals(0.0, $product->getPrice());
        $this->assertEquals(0, $product->getStock());
    }
}