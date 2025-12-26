<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use OpenApi\Attributes as OA;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[OA\Schema(
    schema: 'Order',
    title: 'Order',
    description: 'A customer order with items, payment, and fulfillment details',
    required: ['id', 'customerId', 'items', 'totalAmount', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'customerId', type: 'string', example: 'customer-123'),
        new OA\Property(property: 'customerName', type: 'string', nullable: true, example: 'John Doe'),
        new OA\Property(
            property: 'items',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'itemId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                    new OA\Property(property: 'qty', type: 'integer', example: 2),
                    new OA\Property(property: 'unitPrice', type: 'number', format: 'float', example: 12.99)
                ],
                type: 'object'
            )
        ),
        new OA\Property(property: 'totalAmount', type: 'string', format: 'decimal', example: '25.98'),
        new OA\Property(property: 'status', type: 'string', enum: ['validating', 'reserved', 'paid', 'canceled', 'completed'], example: 'paid'),
        new OA\Property(property: 'reservationId', type: 'string', format: 'uuid', nullable: true, example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'paymentIntentId', type: 'string', format: 'uuid', nullable: true, example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-01-01T12:00:00Z'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-01-01T12:00:00Z')
    ]
)]
class Order
{
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 100)]
    private string $customerId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerName = null;

    #[ORM\Column(type: Types::JSON)]
    private array $items = [];

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_VALIDATING;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $reservationId = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $paymentIntentId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $idempotencyKey = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): static
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(?string $customerName): static
    {
        $this->customerName = $customerName;
        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): static
    {
        $this->items = $items;
        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getReservationId(): ?string
    {
        return $this->reservationId;
    }

    public function setReservationId(?string $reservationId): static
    {
        $this->reservationId = $reservationId;
        return $this;
    }

    public function getPaymentIntentId(): ?string
    {
        return $this->paymentIntentId;
    }

    public function setPaymentIntentId(?string $paymentIntentId): static
    {
        $this->paymentIntentId = $paymentIntentId;
        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
