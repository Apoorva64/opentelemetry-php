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
use Psr\Log\LoggerInterface;
use OpenApi\Attributes as OA;
use InventoryApi\InventoryClient\DefaultApi as InventoryClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use App\Middleware\OpenTelemetryGuzzleMiddleware;

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
    private InventoryClient $inventoryClient;

    public function __construct(
        private EntityManagerInterface $em,
        private MenuItemRepository $menuItemRepository,
        private LoggerInterface $logger,
    ) {
        $stack = HandlerStack::create();
        $stack->push(new OpenTelemetryGuzzleMiddleware(), 'otel_attributes');
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $inventoryConfig = new \InventoryApi\Configuration();
        $inventoryConfig->setHost($_ENV['INVENTORY_SERVICE_URL'] ?? 'http://localhost:8002');
        $this->inventoryClient = new InventoryClient($guzzle, $inventoryConfig);
        
        $this->logger->debug('MenuController initialized', [
            'inventoryServiceUrl' => $_ENV['INVENTORY_SERVICE_URL'] ?? 'http://localhost:8002'
        ]);
    }

    #[Route('/health', name: 'menu_health', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/health',
        operationId: 'getMenuHealth',
        summary: 'Health check',
        description: 'Returns the health status of the menu service'
    )]
    #[OA\Response(
        response: 200,
        description: 'Service is healthy',
        content: new OA\JsonContent(
            type: 'object',
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
        operationId: 'listMenuItems',
        summary: 'List all menu items',
        description: 'Returns a list of all available menu items'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of menu items',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/MenuItem')
                ),
                new OA\Property(property: 'traceId', type: 'string', example: 'trace_abc123')
            ]
        )
    )]
    public function listItems(): JsonResponse
    {
        $this->logger->info('Fetching available menu items');
        
        $items = $this->menuItemRepository->findAvailable();
        
        $this->logger->info('Menu items retrieved', ['count' => count($items)]);
        
        return $this->json([
            'items' => array_map(fn(MenuItem $item) => $this->serializeItem($item), $items),
            'traceId' => uniqid('trace_'),
        ]);
    }

    #[Route('/items/{id}', name: 'menu_item_get', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/items/{id}',
        operationId: 'getMenuItem',
        summary: 'Get a menu item',
        description: 'Returns a single menu item by ID'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Menu item ID',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Menu item details',
        content: new OA\JsonContent(ref: '#/components/schemas/MenuItem')
    )]
    #[OA\Response(
        response: 404,
        description: 'Menu item not found',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'string', example: 'ITEM_NOT_FOUND'),
                        new OA\Property(property: 'message', type: 'string', example: 'Menu item not found'),
                        new OA\Property(property: 'traceId', type: 'string', example: 'trace_abc123')
                    ]
                )
            ]
        )
    )]
    public function getItem(string $id): JsonResponse
    {
        $this->logger->info('Fetching menu item', ['itemId' => $id]);
        
        $item = $this->menuItemRepository->find($id);
        
        if (!$item) {
            $this->logger->warning('Menu item not found', ['itemId' => $id]);
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
        operationId: 'createMenuItem',
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
    #[OA\Response(
        response: 201,
        description: 'Menu item created',
        content: new OA\JsonContent(ref: '#/components/schemas/MenuItem')
    )]
    public function createItem(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $this->logger->info('Creating new menu item', [
            'name' => $data['name'] ?? 'unknown',
            'category' => $data['category'] ?? 'main',
            'price' => $data['price'] ?? '0.00'
        ]);
        
        $item = new MenuItem();
        $item->setName($data['name'] ?? '')
            ->setDescription($data['description'] ?? null)
            ->setPrice($data['price'] ?? '0.00')
            ->setCategory($data['category'] ?? 'main')
            ->setAvailable($data['available'] ?? true)
            ->setIngredients($data['ingredients'] ?? null);
        
        $this->em->persist($item);
        $this->em->flush();
        
        $this->logger->info('Menu item created successfully', [
            'itemId' => $item->getId(),
            'name' => $item->getName()
        ]);
        
        return $this->json($this->serializeItem($item), Response::HTTP_CREATED);
    }

    #[Route('/items/{id}', name: 'menu_item_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/v1/menu/items/{id}',
        operationId: 'updateMenuItem',
        summary: 'Update a menu item',
        description: 'Partially updates an existing menu item'
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Menu item ID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
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
        $this->logger->info('Updating menu item', ['itemId' => $id]);
        
        $item = $this->menuItemRepository->find($id);
        
        if (!$item) {
            $this->logger->warning('Menu item not found for update', ['itemId' => $id]);
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
        
        $this->logger->info('Menu item updated successfully', [
            'itemId' => $id,
            'updatedFields' => array_keys($data)
        ]);
        
        return $this->json($this->serializeItem($item));
    }

    #[Route('/items/{id}/availability', name: 'menu_item_availability', methods: ['POST'])]
    #[OA\Post(
        path: '/v1/menu/items/{id}/availability',
        operationId: 'updateMenuItemAvailability',
        summary: 'Update item availability',
        description: 'Updates availability status of a menu item and reconciles with inventory'
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Menu item ID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
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
        $this->logger->info('Updating menu item availability', ['itemId' => $id]);
        
        $item = $this->menuItemRepository->find($id);
        
        if (!$item) {
            $this->logger->warning('Menu item not found for availability update', ['itemId' => $id]);
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
        $this->logger->info('Calling inventory service to reconcile availability', [
            'itemId' => $id,
            'available' => $available
        ]);
        
        $reconcileRequest = new \InventoryApi\Model\ReconcileInventoryRequest();
        $reconcileRequest->setItemId($id);
        $reconcileRequest->setAvailable($available);
        $reconcileRequest->setIngredients($item->getIngredients());
        
        try {
            $this->inventoryClient->reconcileInventory($reconcileRequest);
            $this->logger->info('Inventory reconciliation successful', ['itemId' => $id]);
        } catch (\Throwable $e) {
            $this->logger->error('Inventory reconciliation failed', [
                'itemId' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
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
        operationId: 'validateMenuItems',
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
                        type: 'object',
                        required: ['itemId', 'qty', 'unitPrice'],
                        properties: [
                            new OA\Property(property: 'itemId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                            new OA\Property(property: 'qty', type: 'integer', example: 2),
                            new OA\Property(property: 'unitPrice', type: 'string', example: '12.99')
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
        
        $this->logger->info('Validating menu items', [
            'itemCount' => count($requestedItems),
            'traceId' => $traceId
        ]);
        
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
        
        $this->logger->info('Menu validation completed', [
            'valid' => $allValid,
            'itemCount' => count($validatedItems),
            'traceId' => $traceId
        ]);
        
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

    // ============================================================================
    // Test Error Endpoints - For observability testing
    // ============================================================================

    #[Route('/test/error/division', name: 'test_division_error', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/division',
        operationId: 'testDivisionError',
        summary: 'Test division by zero error',
        description: 'Triggers a DivisionByZeroError for testing error tracking'
    )]
    #[OA\Response(response: 500, description: 'Division by zero error')]
    public function testDivisionError(): JsonResponse
    {
        $a = 10;
        $b = 0;
        $result = intdiv($a, $b); // DivisionByZeroError
        
        return $this->json(['result' => $result]);
    }

    #[Route('/test/error/type', name: 'test_type_error', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/type',
        operationId: 'testTypeError',
        summary: 'Test type error',
        description: 'Triggers a TypeError for testing error tracking'
    )]
    #[OA\Response(response: 500, description: 'Type error')]
    public function testTypeError(): JsonResponse
    {
        $this->causeTypeError("not an array"); // @phpstan-ignore-line
        
        return $this->json(['status' => 'ok']);
    }

    private function causeTypeError(array $data): void
    {
        // This method expects an array but we pass a string
    }

    #[Route('/test/error/exception', name: 'test_exception', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/exception',
        operationId: 'testException',
        summary: 'Test unhandled exception',
        description: 'Throws an unhandled RuntimeException for testing'
    )]
    #[OA\Response(response: 500, description: 'Runtime exception')]
    public function testException(): JsonResponse
    {
        throw new \RuntimeException('This is a test exception for observability testing', 500);
    }

    #[Route('/test/error/nested-exception', name: 'test_nested_exception', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/nested-exception',
        operationId: 'testNestedException',
        summary: 'Test nested exception chain',
        description: 'Throws an exception with a cause chain for testing'
    )]
    #[OA\Response(response: 500, description: 'Nested exception')]
    public function testNestedException(): JsonResponse
    {
        try {
            throw new \InvalidArgumentException('Root cause: invalid input', 100);
        } catch (\InvalidArgumentException $e) {
            try {
                throw new \LogicException('Middle layer: business logic failed', 200, $e);
            } catch (\LogicException $e2) {
                throw new \RuntimeException('Top level: operation failed', 500, $e2);
            }
        }
    }

    #[Route('/test/error/http/{code}', name: 'test_http_error', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/http/{code}',
        operationId: 'testHttpError',
        summary: 'Test specific HTTP error code',
        description: 'Returns the specified HTTP error code for testing'
    )]
    #[OA\Parameter(name: 'code', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 404))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[OA\Response(response: 500, description: 'Internal server error')]
    #[OA\Response(response: 503, description: 'Service unavailable')]
    public function testHttpError(int $code): JsonResponse
    {
        $messages = [
            400 => 'Bad Request - Invalid input provided',
            401 => 'Unauthorized - Authentication required',
            403 => 'Forbidden - Access denied',
            404 => 'Not Found - Resource does not exist',
            409 => 'Conflict - Resource state conflict',
            422 => 'Unprocessable Entity - Validation failed',
            429 => 'Too Many Requests - Rate limit exceeded',
            500 => 'Internal Server Error - Something went wrong',
            502 => 'Bad Gateway - Upstream service error',
            503 => 'Service Unavailable - Try again later',
            504 => 'Gateway Timeout - Upstream timeout',
        ];

        $message = $messages[$code] ?? "HTTP Error $code";
        
        return $this->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]
        ], $code);
    }

    #[Route('/test/error/memory', name: 'test_memory_error', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/memory',
        operationId: 'testMemoryError',
        summary: 'Test memory exhaustion (use with caution)',
        description: 'Attempts to exhaust memory - will be caught by memory limit'
    )]
    #[OA\Response(response: 500, description: 'Memory exhaustion error')]
    public function testMemoryError(): JsonResponse
    {
        $data = [];
        // This will eventually hit the memory limit
        for ($i = 0; $i < 1000000; $i++) {
            $data[] = str_repeat('x', 10000);
        }
        
        return $this->json(['count' => count($data)]);
    }

    #[Route('/test/error/timeout', name: 'test_timeout', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/timeout',
        operationId: 'testTimeout',
        summary: 'Test slow endpoint (simulates timeout)',
        description: 'Sleeps for specified seconds to simulate slow response'
    )]
    #[OA\Parameter(name: 'seconds', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 5))]
    #[OA\Response(response: 200, description: 'Response after delay')]
    public function testTimeout(Request $request): JsonResponse
    {
        $seconds = min((int) $request->query->get('seconds', 5), 30); // Max 30 seconds
        sleep($seconds);
        
        return $this->json([
            'status' => 'completed',
            'slept_for' => $seconds,
        ]);
    }

    #[Route('/test/error/undefined', name: 'test_undefined_error', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/undefined',
        operationId: 'testUndefinedError',
        summary: 'Test undefined variable/property access',
        description: 'Triggers errors from accessing undefined things'
    )]
    #[OA\Response(response: 500, description: 'Undefined error')]
    public function testUndefinedError(): JsonResponse
    {
        $array = ['a' => 1, 'b' => 2];
        $value = $array['nonexistent']; // Will trigger warning/error
        
        return $this->json(['value' => $value]);
    }

    #[Route('/test/error/database', name: 'test_database_error', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/database',
        operationId: 'testDatabaseError',
        summary: 'Test database error',
        description: 'Triggers a database error with invalid SQL'
    )]
    #[OA\Response(response: 500, description: 'Database error')]
    public function testDatabaseError(): JsonResponse
    {
        $conn = $this->em->getConnection();
        // Invalid SQL to trigger database error
        $conn->executeQuery('SELECT * FROM nonexistent_table_xyz');
        
        return $this->json(['status' => 'ok']);
    }

    #[Route('/test/error/downstream', name: 'test_downstream_error', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/error/downstream',
        operationId: 'testDownstreamError',
        summary: 'Test downstream service error',
        description: 'Calls a non-existent downstream endpoint to test client errors'
    )]
    #[OA\Response(response: 500, description: 'Downstream service error')]
    public function testDownstreamError(): JsonResponse
    {
        // This will fail because the endpoint doesn't exist
        $this->inventoryClient->getIngredientById('nonexistent-id-12345');
        
        return $this->json(['status' => 'ok']);
    }

    #[Route('/test/log-levels', name: 'test_log_levels', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/menu/test/log-levels',
        operationId: 'testLogLevels',
        summary: 'Test all log levels',
        description: 'Emits logs at all levels for testing log aggregation'
    )]
    #[OA\Response(response: 200, description: 'Logs emitted at all levels')]
    public function testLogLevels(): JsonResponse
    {
        $logger = $this->container->get('logger');
        
        $logger->debug('This is a DEBUG message', ['context' => 'test', 'level' => 'debug']);
        $logger->info('This is an INFO message', ['context' => 'test', 'level' => 'info']);
        $logger->notice('This is a NOTICE message', ['context' => 'test', 'level' => 'notice']);
        $logger->warning('This is a WARNING message', ['context' => 'test', 'level' => 'warning']);
        $logger->error('This is an ERROR message', ['context' => 'test', 'level' => 'error']);
        $logger->critical('This is a CRITICAL message', ['context' => 'test', 'level' => 'critical']);
        $logger->alert('This is an ALERT message', ['context' => 'test', 'level' => 'alert']);
        
        return $this->json([
            'status' => 'ok',
            'message' => 'Logs emitted at all levels',
            'levels' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert'],
        ]);
    }
}
