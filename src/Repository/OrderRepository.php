<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly int $itemsPerPage = 20
    ) {
        parent::__construct($registry, Order::class);
    }

    public function findUserOrders(User $user, int $page = 1): array
    {
        $query = $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC');

        return $this->paginate($query, $page);
    }

    public function findOrdersByStatus(string $status, int $page = 1): array
    {
        $query = $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC');

        return $this->paginate($query, $page);
    }

    public function findOrder(int $orderId): ?Order
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->setParameter('id', $orderId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAll(int $page = 1): array
    {
        $query = $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');

        return $this->paginate($query, $page);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
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
}