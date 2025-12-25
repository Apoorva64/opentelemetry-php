<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    public function findByItemId(string $itemId): ?Stock
    {
        return $this->findOneBy(['itemId' => $itemId]);
    }

    public function findByItemIds(array $itemIds): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.itemId IN (:ids)')
            ->setParameter('ids', $itemIds)
            ->getQuery()
            ->getResult();
    }
}
