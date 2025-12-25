<?php

namespace App\Controller;

use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Menu Service API',
    description: 'Restaurant menu management service'
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Menu Service')]
#[Route('/v1/menu')]
#[OA\Tag(name: 'Menu', description: 'Menu item operations')]
class MenuController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MenuItemRepository $menuItemRepository,
        private HttpClientInterface $httpClient,
    ) {}

    #[Route('/health', name: 'menu_health', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/health',
        summary: 'Health check',
        description: 'Returns the health status of the menu service'
    )]
    #[OA\Response(
        response: 200,
        description: 'Service is healthy',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ok'),
                new OA\Property(property: 'service', type: 'string', example: 'menu')
            ]
        )
    )]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok', 'service' => 'menu']);
    }

    #[Route('/items', name: 'menu_items_list', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/items',
        summary: 'List all menu items',
        description: 'Returns a list of all available menu items'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of menu items',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/MenuItem')
                ),
                new OA\Property(property: 'traceId', type: 'string')
            ]
        )
    )]
    public function listItems(): JsonResponse
    {
        $items = $this->menuItemRepository->findAvailable();
        
        return $this->json([
            'items' => array_map(fn(MenuItem $item) => $this->serializeItem($item), $items),
            'traceId' => uniqid('trace_'),
        ]);
    }

    #[Route('/items/{id}', name: 'menu_item_get', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/items/{id}',
        summary: 'Get a menu item',
        description: 'Returns a single menu item by ID'
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Menu item ID', required: true)]
    #[OA\Response(
        response: 200,
        description: 'Menu item details',
        content: new OA\JsonContent(ref: '#/components/schemas/MenuItem')
    )]
    #[OA\Response(response: 404, description: 'Menu item not found')]
    public function getItem(string $id): JsonResponse
    {
        $item = $this->menuItemRepository->find($id);
        
        if (!$item) {
            return $this->json([
                'error' => [
                    'code' => 'ITEM_NOT_FOUND',
                    'message' => "Menu item {$id} not found",
                    'traceId' => uniqid('trace_'),
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        return $this->json($this->serializeItem($item));
    }

    #[Route('/items', name: 'menu_item_create', methods: ['POST'])]
    #[OA\Post(
        path: '/v1/menu/items',
        summary: 'Create a menu item',
        description: 'Creates a new menu item'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'price'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Margherita Pizza'),
                new OA\Property(property: 'description', type: 'string', example: 'Classic tomato and mozzarella'),
                new OA\Property(property: 'price', type: 'string', example: '12.99'),
                new OA\Property(property: 'category', type: 'string', example: 'main'),
                new OA\Property(property: 'available', type: 'boolean', example: true),
                new OA\Property(property: 'ingredients', type: 'array', items: new OA\Items(type: 'string'))
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Menu item created')]
    public function createItem(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $item = new MenuItem();
        $item->setName($data['name'] ?? '')
            ->setDescription($data['description'] ?? null)
            ->setPrice($data['price'] ?? '0.00')
            ->setCategory($data['category'] ?? 'main')
            ->setAvailable($data['available'] ?? true)
            ->setIngredients($data['ingredients'] ?? null);
        
        $this->em->persist($item);
        $this->em->flush();
        
        return $this->json($this->serializeItem($item), Response::HTTP_CREATED);
    }

    #[Route('/items/{id}', name: 'menu_item_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/v1/menu/items/{id}',
        summary: 'Update a menu item',
        description: 'Partially updates an existing menu item'
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Menu item ID', required: true)]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'price', type: 'string'),
                new OA\Property(property: 'category', type: 'string'),
                new OA\Property(property: 'available', type: 'boolean'),
                new OA\Property(property: 'ingredients', type: 'array', items: new OA\Items(type: 'string'))
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Menu item updated')]
    #[OA\Response(response: 404, description: 'Menu item not found')]
    public function updateItem(string $id, Request $request): JsonResponse
    {
        $item = $this->menuItemRepository->find($id);
        
        if (!$item) {
            return $this->json([
                'error' => [
                    'code' => 'ITEM_NOT_FOUND',
                    'message' => "Menu item {$id} not found",
                    'traceId' => uniqid('trace_'),
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name'])) $item->setName($data['name']);
        if (isset($data['description'])) $item->setDescription($data['description']);
        if (isset($data['price'])) $item->setPrice($data['price']);
        if (isset($data['category'])) $item->setCategory($data['category']);
        if (isset($data['available'])) $item->setAvailable($data['available']);
        if (isset($data['ingredients'])) $item->setIngredients($data['ingredients']);
        
        $item->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        return $this->json($this->serializeItem($item));
    }

    #[Route('/items/{id}/availability', name: 'menu_item_availability', methods: ['POST'])]
    #[OA\Post(
        path: '/v1/menu/items/{id}/availability',
        summary: 'Update item availability',
        description: 'Updates availability status of a menu item and reconciles with inventory'
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Menu item ID', required: true)]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['available'],
            properties: [
                new OA\Property(property: 'available', type: 'boolean', example: true)
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Availability updated')]
    #[OA\Response(response: 404, description: 'Menu item not found')]
    public function updateAvailability(string $id, Request $request): JsonResponse
    {
        $item = $this->menuItemRepository->find($id);
        
        if (!$item) {
            return $this->json([
                'error' => [
                    'code' => 'ITEM_NOT_FOUND',
                    'message' => "Menu item {$id} not found",
                    'traceId' => uniqid('trace_'),
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        $data = json_decode($request->getContent(), true);
        $available = $data['available'] ?? false;
        
        $item->setAvailable($available);
        $item->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        // Call InventoryService to reconcile availability
        try {
            $inventoryUrl = $_ENV['INVENTORY_SERVICE_URL'] ?? 'http://localhost:8002';
            $this->httpClient->request('POST', "{$inventoryUrl}/v1/inventory/reconcile", [
                'json' => [
                    'itemId' => $id,
                    'available' => $available,
                    'ingredients' => $item->getIngredients(),
                ],
            ]);
        } catch (\Throwable $e) {
            // Log but don't fail the request
        }
        
        return $this->json([
            'itemId' => $id,
            'available' => $available,
            'traceId' => uniqid('trace_'),
        ]);
    }

    #[Route('/validation', name: 'menu_validation', methods: ['POST'])]
    #[OA\Post(
        path: '/v1/menu/validation',
        summary: 'Validate menu items',
        description: 'Validates a list of menu items for availability and pricing'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['items'],
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'itemId', type: 'string'),
                            new OA\Property(property: 'quantity', type: 'integer')
                        ]
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Validation result',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'valid', type: 'boolean'),
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'totalPrice', type: 'string'),
                new OA\Property(property: 'traceId', type: 'string')
            ]
        )
    )]
    public function validateItems(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $requestedItems = $data['items'] ?? [];
        $traceId = uniqid('trace_');
        
        $itemIds = array_column($requestedItems, 'itemId');
        $menuItems = $this->menuItemRepository->findByIds($itemIds);
        $menuItemsById = [];
        foreach ($menuItems as $item) {
            $menuItemsById[$item->getId()] = $item;
        }
        
        $validatedItems = [];
        $allValid = true;
        
        foreach ($requestedItems as $requested) {
            $itemId = $requested['itemId'];
            $menuItem = $menuItemsById[$itemId] ?? null;
            
            if (!$menuItem) {
                $validatedItems[] = [
                    'itemId' => $itemId,
                    'valid' => false,
                    'error' => 'ITEM_NOT_FOUND',
                ];
                $allValid = false;
                continue;
            }
            
            $isAvailable = $menuItem->isAvailable();
            $currentPrice = $menuItem->getPrice();
            $requestedPrice = $requested['unitPrice'] ?? 0;
            $priceMatches = abs((float)$currentPrice - (float)$requestedPrice) < 0.01;
            
            $valid = $isAvailable && $priceMatches;
            if (!$valid) $allValid = false;
            
            $validatedItems[] = [
                'itemId' => $itemId,
                'qty' => $requested['qty'] ?? 1,
                'unitPrice' => $requestedPrice,
                'currentPrice' => $currentPrice,
                'available' => $isAvailable,
                'valid' => $valid,
                'error' => !$isAvailable ? 'ITEM_UNAVAILABLE' : (!$priceMatches ? 'PRICE_MISMATCH' : null),
            ];
        }
        
        return $this->json([
            'valid' => $allValid,
            'validatedItems' => $validatedItems,
            'traceId' => $traceId,
        ]);
    }

    private function serializeItem(MenuItem $item): array
    {
        return [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'description' => $item->getDescription(),
            'price' => $item->getPrice(),
            'category' => $item->getCategory(),
            'available' => $item->isAvailable(),
            'ingredients' => $item->getIngredients(),
            'createdAt' => $item->getCreatedAt()->format('c'),
            'updatedAt' => $item->getUpdatedAt()->format('c'),
        ];
    }
}
