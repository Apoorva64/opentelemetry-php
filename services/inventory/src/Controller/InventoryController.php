<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Stock;
use App\Repository\ReservationRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use MenuApi\MenuClient\DefaultApi as MenuClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use App\Middleware\OpenTelemetryGuzzleMiddleware;

#[OA\Info(
    version: '1.0.0',
    title: 'Inventory Service API',
    description: 'Restaurant inventory and stock management'
)]
#[OA\Server(url: 'http://localhost:8002', description: 'Inventory Service')]
#[Route('/v1/inventory')]
#[OA\Tag(name: 'Inventory', description: 'Inventory and stock management')]
class InventoryController extends AbstractController
{
    private MenuClient $menuClient;

    public function __construct(
        private EntityManagerInterface $em,
        private ReservationRepository $reservationRepository,
        private StockRepository $stockRepository,
    ) {
        $stack = HandlerStack::create();
        $stack->push(new OpenTelemetryGuzzleMiddleware(), 'otel_attributes');
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $menuConfig = new \MenuApi\Configuration();
        $menuConfig->setHost($_ENV['MENU_SERVICE_URL'] ?? 'http://localhost:8000');
        $this->menuClient = new MenuClient($guzzle, $menuConfig);
    }

    #[Route('/health', name: 'inventory_health', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/inventory/health',
        operationId: 'getInventoryHealth',
        summary: 'Health check',
        description: 'Returns the health status of the inventory service'
    )]
    #[OA\Response(
        response: 200,
        description: 'Service is healthy',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ok'),
                new OA\Property(property: 'service', type: 'string', example: 'inventory')
            ]
        )
    )]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok', 'service' => 'inventory']);
    }

    #[Route('/reservations', name: 'inventory_reserve', methods: ['POST'])]
    #[OA\Post(
        path: '/v1/inventory/reservations',
        operationId: 'createReservation',
        summary: 'Create a reservation',
        description: 'Creates a stock reservation for an order'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['orderId', 'items'],
            properties: [
                new OA\Property(property: 'idempotencyKey', type: 'string', example: 'reservation-123-abc'),
                new OA\Property(property: 'orderId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'itemId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                            new OA\Property(property: 'qty', type: 'integer', example: 2),
                            new OA\Property(property: 'name', type: 'string', example: 'Margherita Pizza')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Reservation created',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'reservationId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                new OA\Property(property: 'status', type: 'string', example: 'reserved'),
                new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', example: '2025-01-01T12:15:00Z'),
                new OA\Property(property: 'traceId', type: 'string', example: 'trace_abc123')
            ]
        )
    )]
    #[OA\Response(
        response: 409,
        description: 'Insufficient stock',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'string', example: 'INSUFFICIENT_STOCK'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'details',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'itemId', type: 'string'),
                                new OA\Property(property: 'requested', type: 'integer'),
                                new OA\Property(property: 'available', type: 'integer')
                            ]
                        ),
                        new OA\Property(property: 'traceId', type: 'string')
                    ]
                )
            ]
        )
    )]
    public function createReservation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $traceId = uniqid('trace_');
        $idempotencyKey = $data['idempotencyKey'] ?? null;
        
        // Check for idempotent request
        if ($idempotencyKey) {
            $existing = $this->reservationRepository->findByIdempotencyKey($idempotencyKey);
            if ($existing) {
                return $this->json([
                    'reservationId' => $existing->getId(),
                    'status' => $existing->getStatus(),
                    'expiresAt' => $existing->getExpiresAt()->format('c'),
                    'traceId' => $traceId,
                ]);
            }
        }
        
        $orderId = $data['orderId'] ?? '';
        $items = $data['items'] ?? [];
        
        // Check stock availability for all items
        $itemIds = array_column($items, 'itemId');
        $stockItems = $this->stockRepository->findByItemIds($itemIds);
        $stockByItemId = [];
        foreach ($stockItems as $stock) {
            $stockByItemId[$stock->getItemId()] = $stock;
        }
        
        // Verify we have enough stock
        foreach ($items as $item) {
            $itemId = $item['itemId'];
            $qty = $item['qty'] ?? 1;
            $stock = $stockByItemId[$itemId] ?? null;
            
            if (!$stock) {
                // Auto-create stock with default quantity for demo
                $stock = new Stock();
                $stock->setItemId($itemId)
                    ->setItemName($item['name'] ?? $itemId)
                    ->setQuantity(100) // Default stock for demo
                    ->setReservedQuantity(0);
                $this->em->persist($stock);
                $stockByItemId[$itemId] = $stock;
            }
            
            if ($stock->getAvailableQuantity() < $qty) {
                return $this->json([
                    'error' => [
                        'code' => 'INSUFFICIENT_STOCK',
                        'message' => "Insufficient stock for item {$itemId}",
                        'details' => [
                            'itemId' => $itemId,
                            'requested' => $qty,
                            'available' => $stock->getAvailableQuantity(),
                        ],
                        'traceId' => $traceId,
                    ]
                ], Response::HTTP_CONFLICT);
            }
        }
        
        // Reserve stock
        foreach ($items as $item) {
            $itemId = $item['itemId'];
            $qty = $item['qty'] ?? 1;
            $stock = $stockByItemId[$itemId];
            $stock->setReservedQuantity($stock->getReservedQuantity() + $qty);
            $stock->setUpdatedAt(new \DateTimeImmutable());
        }
        
        // Create reservation
        $reservation = new Reservation();
        $reservation->setOrderId($orderId)
            ->setItems($items)
            ->setStatus(Reservation::STATUS_RESERVED)
            ->setIdempotencyKey($idempotencyKey);
        
        $this->em->persist($reservation);
        $this->em->flush();
        
        return $this->json([
            'reservationId' => $reservation->getId(),
            'status' => $reservation->getStatus(),
            'expiresAt' => $reservation->getExpiresAt()->format('c'),
            'traceId' => $traceId,
        ], Response::HTTP_CREATED);
    }

    #[Route('/reservations/{id}/commit', name: 'inventory_commit', methods: ['POST'])]
    #[OA\Post(path: '/v1/inventory/reservations/{id}/commit', operationId: 'commitReservation', summary: 'Commit a reservation', description: 'Commits a reservation - deducts stock and finalizes')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Reservation ID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Reservation committed')]
    #[OA\Response(response: 404, description: 'Reservation not found')]
    #[OA\Response(response: 409, description: 'Invalid reservation state')]
    public function commitReservation(string $id): JsonResponse
    {
        $reservation = $this->reservationRepository->find($id);
        $traceId = uniqid('trace_');
        
        if (!$reservation) {
            return $this->json([
                'error' => [
                    'code' => 'RESERVATION_NOT_FOUND',
                    'message' => "Reservation {$id} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($reservation->getStatus() !== Reservation::STATUS_RESERVED) {
            return $this->json([
                'error' => [
                    'code' => 'RESERVATION_INVALID_STATE',
                    'message' => "Reservation is in {$reservation->getStatus()} state",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_CONFLICT);
        }
        
        // Deduct from actual stock, release reserved
        $items = $reservation->getItems();
        $itemIds = array_column($items, 'itemId');
        $stockItems = $this->stockRepository->findByItemIds($itemIds);
        $stockByItemId = [];
        foreach ($stockItems as $stock) {
            $stockByItemId[$stock->getItemId()] = $stock;
        }
        
        foreach ($items as $item) {
            $itemId = $item['itemId'];
            $qty = $item['qty'] ?? 1;
            $stock = $stockByItemId[$itemId] ?? null;
            
            if ($stock) {
                $stock->setQuantity($stock->getQuantity() - $qty);
                $stock->setReservedQuantity($stock->getReservedQuantity() - $qty);
                $stock->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        
        $reservation->setStatus(Reservation::STATUS_COMMITTED);
        $this->em->flush();
        
        return $this->json([
            'reservationId' => $reservation->getId(),
            'status' => $reservation->getStatus(),
            'traceId' => $traceId,
        ]);
    }

    #[Route('/reservations/{id}/release', name: 'inventory_release', methods: ['POST'])]
    #[OA\Post(path: '/v1/inventory/reservations/{id}/release', operationId: 'releaseReservation', summary: 'Release a reservation', description: 'Releases a reservation - returns reserved stock')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Reservation ID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Reservation released')]
    #[OA\Response(response: 404, description: 'Reservation not found')]
    public function releaseReservation(string $id): JsonResponse
    {
        $reservation = $this->reservationRepository->find($id);
        $traceId = uniqid('trace_');
        
        if (!$reservation) {
            return $this->json([
                'error' => [
                    'code' => 'RESERVATION_NOT_FOUND',
                    'message' => "Reservation {$id} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($reservation->getStatus() !== Reservation::STATUS_RESERVED) {
            return $this->json([
                'reservationId' => $reservation->getId(),
                'status' => $reservation->getStatus(),
                'traceId' => $traceId,
            ]);
        }
        
        // Release reserved quantity
        $items = $reservation->getItems();
        $itemIds = array_column($items, 'itemId');
        $stockItems = $this->stockRepository->findByItemIds($itemIds);
        $stockByItemId = [];
        foreach ($stockItems as $stock) {
            $stockByItemId[$stock->getItemId()] = $stock;
        }
        
        foreach ($items as $item) {
            $itemId = $item['itemId'];
            $qty = $item['qty'] ?? 1;
            $stock = $stockByItemId[$itemId] ?? null;
            
            if ($stock) {
                $stock->setReservedQuantity(max(0, $stock->getReservedQuantity() - $qty));
                $stock->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        
        $reservation->setStatus(Reservation::STATUS_RELEASED);
        $this->em->flush();
        
        return $this->json([
            'reservationId' => $reservation->getId(),
            'status' => $reservation->getStatus(),
            'traceId' => $traceId,
        ]);
    }

    #[Route('/reconcile', name: 'inventory_reconcile', methods: ['POST'])]
    #[OA\Post(path: '/v1/inventory/reconcile', operationId: 'reconcileInventory', summary: 'Reconcile inventory', description: 'Reconciles inventory based on menu item availability changes')]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'itemId', type: 'string'),
                new OA\Property(property: 'available', type: 'boolean'),
                new OA\Property(property: 'ingredients', type: 'array', items: new OA\Items(type: 'string'))
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Reconciliation complete')]
    public function reconcile(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $traceId = uniqid('trace_');
        
        $itemId = $data['itemId'] ?? null;
        $available = $data['available'] ?? true;
        $ingredients = $data['ingredients'] ?? [];
        
        // Update stock availability based on menu item status
        if ($itemId) {
            $stock = $this->stockRepository->findByItemId($itemId);
            if (!$stock) {
                $stock = new Stock();
                $stock->setItemId($itemId)
                    ->setItemName($itemId)
                    ->setQuantity($available ? 100 : 0)
                    ->setReservedQuantity(0);
                $this->em->persist($stock);
            } else {
                if (!$available) {
                    $stock->setQuantity(0);
                }
            }
            $stock->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();
        }
        
        return $this->json([
            'reconciled' => true,
            'itemId' => $itemId,
            'traceId' => $traceId,
        ]);
    }

    #[Route('/stock', name: 'inventory_stock_list', methods: ['GET'])]
    #[OA\Get(path: '/v1/inventory/stock', operationId: 'listStock', summary: 'List stock', description: 'Lists all stock items')]
    #[OA\Response(response: 200, description: 'Stock items list')]
    public function listStock(): JsonResponse
    {
        $stocks = $this->stockRepository->findAll();
        
        return $this->json([
            'items' => array_map(fn(Stock $s) => [
                'id' => $s->getId(),
                'itemId' => $s->getItemId(),
                'itemName' => $s->getItemName(),
                'quantity' => $s->getQuantity(),
                'reservedQuantity' => $s->getReservedQuantity(),
                'availableQuantity' => $s->getAvailableQuantity(),
                'updatedAt' => $s->getUpdatedAt()->format('c'),
            ], $stocks),
            'traceId' => uniqid('trace_'),
        ]);
    }

    #[Route('/stock/{itemId}', name: 'inventory_stock_update', methods: ['PUT'])]
    #[OA\Put(path: '/v1/inventory/stock/{itemId}', operationId: 'updateStock', summary: 'Update stock', description: 'Updates stock quantity for an item')]
    #[OA\Parameter(name: 'itemId', in: 'path', description: 'Menu item ID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'quantity', type: 'integer', example: 100),
                new OA\Property(property: 'itemName', type: 'string')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Stock updated')]
    public function updateStock(string $itemId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $traceId = uniqid('trace_');
        
        $stock = $this->stockRepository->findByItemId($itemId);
        if (!$stock) {
            $stock = new Stock();
            $stock->setItemId($itemId)
                ->setItemName($data['itemName'] ?? $itemId);
            $this->em->persist($stock);
        }
        
        if (isset($data['quantity'])) {
            $stock->setQuantity((int) $data['quantity']);
        }
        if (isset($data['itemName'])) {
            $stock->setItemName($data['itemName']);
        }
        
        $stock->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        // Optionally notify MenuService about availability
        $available = $stock->getAvailableQuantity() > 0;
        
        try {
            $availabilityRequest = new \MenuApi\Model\UpdateMenuItemAvailabilityRequest();
            $availabilityRequest->setAvailable($available);
            
            $this->menuClient->updateMenuItemAvailability($itemId, $availabilityRequest);
        } catch (\Throwable $e) {
            // Log but don't fail
        }
        
        return $this->json([
            'id' => $stock->getId(),
            'itemId' => $stock->getItemId(),
            'itemName' => $stock->getItemName(),
            'quantity' => $stock->getQuantity(),
            'reservedQuantity' => $stock->getReservedQuantity(),
            'availableQuantity' => $stock->getAvailableQuantity(),
            'traceId' => $traceId,
        ]);
    }
}
