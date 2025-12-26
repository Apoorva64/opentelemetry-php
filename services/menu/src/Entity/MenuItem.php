<?php

namespace App\Entity;

use App\Repository\MenuItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use OpenApi\Attributes as OA;

#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[OA\Schema(
    schema: 'MenuItem',
    title: 'Menu Item',
    description: 'A menu item available in the restaurant',
    required: ['id', 'name', 'price', 'category', 'available'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'name', type: 'string', example: 'Margherita Pizza'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Classic tomato and mozzarella'),
        new OA\Property(property: 'price', type: 'string', format: 'decimal', example: '12.99'),
        new OA\Property(property: 'category', type: 'string', example: 'main'),
        new OA\Property(property: 'available', type: 'boolean', example: true),
        new OA\Property(property: 'ingredients', type: 'array', items: new OA\Items(type: 'string'), nullable: true, example: ['tomato', 'mozzarella', 'basil']),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-01-01T12:00:00Z'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2025-01-01T12:00:00Z')
    ]
)]
class MenuItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column(length: 100)]
    private string $category;

    #[ORM\Column]
    private bool $available = true;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $ingredients = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): static
    {
        $this->available = $available;
        return $this;
    }

    public function getIngredients(): ?array
    {
        return $this->ingredients;
    }

    public function setIngredients(?array $ingredients): static
    {
        $this->ingredients = $ingredients;
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
