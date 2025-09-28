<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly int $itemsPerPage = 20
    ) {
        parent::__construct($registry, Product::class);
    }

    public function findActiveProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.stock > 0')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findProductsWithLowStock(int $threshold = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.stock <= :threshold')
            ->andWhere('p.stock > 0')
            ->andWhere('p.isActive = :active')
            ->setParameter('threshold', $threshold)
            ->setParameter('active', true)
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchProducts(string $searchTerm, int $page = 1): array
    {
        $query = $this->createQueryBuilder('p')
            ->andWhere('p.name LIKE :searchTerm OR p.description LIKE :searchTerm')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.stock > 0')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC');

        return $this->paginate($query, $page);
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    private function paginate(QueryBuilder $queryBuilder, int $page): array
    {
        return $queryBuilder
            ->setFirstResult(($page - 1) * $this->itemsPerPage)
            ->setMaxResults($this->itemsPerPage)
            ->getQuery()
            ->getResult();
    }

    public function decreaseStock(int $productId, int $quantity): void
    {
        $this->createQueryBuilder('p')
            ->update()
            ->set('p.stock', 'p.stock - :quantity')
            ->andWhere('p.id = :id')
            ->andWhere('p.stock >= :quantity')
            ->setParameter('quantity', $quantity)
            ->setParameter('id', $productId)
            ->getQuery()
            ->execute();
    }
}