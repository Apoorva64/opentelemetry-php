<?php

namespace App\Repository;

use App\Entity\PaymentIntent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentIntent>
 */
class PaymentIntentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentIntent::class);
    }

    public function findByIdempotencyKey(string $key): ?PaymentIntent
    {
        return $this->findOneBy(['idempotencyKey' => $key]);
    }

    public function findByOrderId(string $orderId): ?PaymentIntent
    {
        return $this->findOneBy(['orderId' => $orderId]);
    }
}
