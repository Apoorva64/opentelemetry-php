<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findByIdempotencyKey(string $key): ?Reservation
    {
        return $this->findOneBy(['idempotencyKey' => $key]);
    }

    public function findByOrderId(string $orderId): ?Reservation
    {
        return $this->findOneBy(['orderId' => $orderId]);
    }
}
