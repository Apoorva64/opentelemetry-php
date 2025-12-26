<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use OpenApi\Attributes as OA;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[OA\Schema(
    schema: 'Reservation',
    title: 'Inventory Reservation',
    description: 'A temporary reservation of inventory items for an order',
    required: ['id', 'orderId', 'items', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'orderId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(
            property: 'items',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'itemId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                    new OA\Property(property: 'qty', type: 'integer', example: 2),
                    new OA\Property(property: 'name', type: 'string', example: 'Margherita Pizza')
                ],
                type: 'object'
            )
        ),
        new OA\Property(property: 'status', type: 'string', enum: ['reserved', 'committed', 'released', 'expired'], example: 'reserved'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-01-01T12:00:00Z'),
        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', example: '2025-01-01T12:15:00Z')
    ]
)]
class Reservation
{
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $orderId;

    #[ORM\Column(type: Types::JSON)]
    private array $items = [];

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_RESERVED;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $idempotencyKey = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+15 minutes');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): static
    {
        $this->orderId = $orderId;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }
}
