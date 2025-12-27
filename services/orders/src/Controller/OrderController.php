<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use BillingApi\BillingClient\DefaultApi as BillingClient;
use InventoryApi\InventoryClient\DefaultApi as InventoryClient;
use MenuApi\MenuClient\DefaultApi as MenuClient;
use GuzzleHttp\Client as GuzzleClient;

#[OA\Info(
    version: '1.0.0',
    title: 'Orders Service API',
    description: 'Restaurant order management and workflow orchestration'
)]
#[OA\Server(url: 'http://localhost:8001', description: 'Orders Service')]
#[Route('/v1/orders')]
#[OA\Tag(name: 'Orders', description: 'Order management and workflow orchestration')]
class OrderController extends AbstractController
{
    private BillingClient $billingClient;
    private InventoryClient $inventoryClient;
    private MenuClient $menuClient;

    public function __construct(
        private EntityManagerInterface $em,
        private OrderRepository $orderRepository
    ) {
        $guzzle = new GuzzleClient();
        
        $billingConfig = new \BillingApi\Configuration();
        $billingConfig->setHost($_ENV['BILLING_SERVICE_URL'] ?? 'http://localhost:8003');
        $this->billingClient = new BillingClient($guzzle, $billingConfig);
        
        $inventoryConfig = new \InventoryApi\Configuration();
        $inventoryConfig->setHost($_ENV['INVENTORY_SERVICE_URL'] ?? 'http://localhost:8002');
        $this->inventoryClient = new InventoryClient($guzzle, $inventoryConfig);
        
        $menuConfig = new \MenuApi\Configuration();
        $menuConfig->setHost($_ENV['MENU_SERVICE_URL'] ?? 'http://localhost:8000');
        $this->menuClient = new MenuClient($guzzle, $menuConfig);
    }

    #[Route('/health', name: 'orders_health', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/orders/health',
        operationId: 'getOrdersHealth',
        summary: 'Health check',
        description: 'Returns the health status of the orders service'
    )]
    #[OA\Response(
        response: 200,
        description: 'Service is healthy',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ok'),
                new OA\Property(property: 'service', type: 'string', example: 'orders')
            ]
        )
    )]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok', 'service' => 'orders']);
    }

    #[Route('', name: 'order_create', methods: ['POST'])]
    #[OA\Post(
        path: '/v1/orders',
        operationId: 'createOrder',
        summary: 'Create an order',
        description: 'Creates a new order, validates with menu service, reserves inventory, and creates payment intent'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['customer', 'items'],
            properties: [
                new OA\Property(property: 'idempotencyKey', type: 'string', example: 'order-123-abc'),
                new OA\Property(
                    property: 'customer',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'customer-123'),
                        new OA\Property(property: 'name', type: 'string', example: 'John Doe')
                    ]
                ),
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'itemId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                            new OA\Property(property: 'qty', type: 'integer', example: 2),
                            new OA\Property(property: 'unitPrice', type: 'number', format: 'float', example: 12.99)
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Order created',
        content: new OA\JsonContent(ref: '#/components/schemas/Order')
    )]
    #[OA\Response(
        response: 400,
        description: 'Menu validation failed',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'string', example: 'MENU_VALIDATION_FAILED'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'details', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'traceId', type: 'string')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 503,
        description: 'Service unavailable',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'string'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'traceId', type: 'string')
                    ]
                )
            ]
        )
    )]
    public function createOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $traceId = uniqid('trace_');
        $idempotencyKey = $data['idempotencyKey'] ?? null;
        
        // Check for idempotent request
        if ($idempotencyKey) {
            $existing = $this->orderRepository->findByIdempotencyKey($idempotencyKey);
            if ($existing) {
                return $this->json($this->serializeOrder($existing));
            }
        }
        
        $customer = $data['customer'] ?? [];
        $items = $data['items'] ?? [];
        
        // Step 1: Validate items with MenuService
        try {
            $validateRequest = new \MenuApi\Model\ValidateMenuItemsRequest();
            $validateRequest->setItems(array_map(function($item) {
                $itemRequest = new \MenuApi\Model\ValidateMenuItemsRequestItemsInner();
                $itemRequest->setItemId($item['itemId'] ?? '');
                $itemRequest->setQty($item['qty'] ?? 1);
                $itemRequest->setUnitPrice($item['unitPrice'] ?? 0);
                return $itemRequest;
            }, $items));
            
            $validationResponse = $this->menuClient->validateMenuItems($validateRequest);
            
            if (!$validationResponse->getValid()) {
                return $this->json([
                    'error' => [
                        'code' => 'MENU_VALIDATION_FAILED',
                        'message' => 'One or more items failed validation',
                        'details' => $validationResponse-> getItems(),
                        'traceId' => $traceId,
                    ]
                ], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Throwable $e) {
            return $this->json([
                'error' => [
                    'code' => 'MENU_SERVICE_UNAVAILABLE',
                    'message' => 'Could not validate items with menu service',
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        
        // Calculate total
        $total = 0;
        foreach ($items as $item) {
            $total += ($item['unitPrice'] ?? 0) * ($item['qty'] ?? 1);
        }
        
        // Create order
        $order = new Order();
        $order->setCustomerId($customer['id'] ?? 'anonymous')
            ->setCustomerName($customer['name'] ?? null)
            ->setItems($items)
            ->setTotalAmount(number_format($total, 2, '.', ''))
            ->setStatus(Order::STATUS_VALIDATING)
            ->setIdempotencyKey($idempotencyKey);
        
        $this->em->persist($order);
        $this->em->flush();
        
        // Step 2: Reserve inventory
        try {
            $reservationRequest = new \InventoryApi\Model\CreateReservationRequest();
            $reservationRequest->setOrderId($order->getId());
            $reservationRequest->setItems(array_map(function($item) {
                $itemReq = new \InventoryApi\Model\CreateReservationRequestItemsInner();
                $itemReq->setItemId($item['itemId'] ?? '');
                $itemReq->setQty($item['qty'] ?? 1);
                $itemReq->setName($item['name'] ?? '');
                return $itemReq;
            }, $items));
            $reservationRequest->setIdempotencyKey($idempotencyKey . '_reservation');
            
            $reservationResponse = $this->inventoryClient->createReservation($reservationRequest);
            
            $order->setReservationId($reservationResponse->getReservationId());
            $order->setStatus(Order::STATUS_RESERVED);
        } catch (\Throwable $e) {
            $order->setStatus(Order::STATUS_CANCELED);
            $this->em->flush();
            
            return $this->json([
                'error' => [
                    'code' => 'INVENTORY_RESERVE_FAILED',
                    'message' => 'Could not reserve inventory',
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        
        // Step 3: Create payment intent
        try {
            $paymentRequest = new \BillingApi\Model\CreatePaymentIntentRequest();
            $paymentRequest->setOrderId($order->getId());
            $paymentRequest->setAmount((float) $order->getTotalAmount());
            $paymentRequest->setCurrency('USD');
            $paymentRequest->setIdempotencyKey($idempotencyKey . '_payment');
            
            $paymentResponse = $this->billingClient->createPaymentIntent($paymentRequest);
            
            $order->setPaymentIntentId($paymentResponse->getPaymentIntentId());
        } catch (\Throwable $e) {
            // Payment intent creation failed - release reservation
            try {
                $this->inventoryClient->releaseReservation($order->getReservationId());
            } catch (\Throwable $releaseError) {
                // Log but continue
            }
            
            $order->setStatus(Order::STATUS_CANCELED);
            $this->em->flush();
            
            return $this->json([
                'error' => [
                    'code' => 'PAYMENT_INTENT_FAILED',
                    'message' => 'Could not create payment intent',
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        return $this->json($this->serializeOrder($order), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'order_get', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/orders/{id}',
        operationId: 'getOrder',
        summary: 'Get an order',
        description: 'Retrieves order details by ID'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Order ID',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Order found',
        content: new OA\JsonContent(ref: '#/components/schemas/Order')
    )]
    #[OA\Response(
        response: 404,
        description: 'Order not found',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'string', example: 'ORDER_NOT_FOUND'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'traceId', type: 'string')
                    ]
                )
            ]
        )
    )]
    public function getOrder(string $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        
        if (!$order) {
            return $this->json([
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => "Order {$id} not found",
                    'traceId' => uniqid('trace_'),
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        return $this->json($this->serializeOrder($order));
    }

    #[Route('/{id}/cancel', name: 'order_cancel', methods: ['POST'])]
    #[OA\Post(path: '/v1/orders/{id}/cancel', operationId: 'cancelOrder', summary: 'Cancel an order', description: 'Cancels an order, releases inventory reservation and processes refund if paid')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Order ID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Order canceled')]
    #[OA\Response(response: 404, description: 'Order not found')]
    #[OA\Response(response: 503, description: 'Refund failed')]
    public function cancelOrder(string $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        $traceId = uniqid('trace_');
        
        if (!$order) {
            return $this->json([
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => "Order {$id} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($order->getStatus() === Order::STATUS_CANCELED) {
            return $this->json($this->serializeOrder($order));
        }
        
        // If paid, refund first
        if ($order->getStatus() === Order::STATUS_PAID && $order->getPaymentIntentId()) {
            try {
                $refundRequest = new \BillingApi\Model\CreateRefundRequest();
                $refundRequest->setOrderId($order->getId());
                $refundRequest->setPaymentIntentId($order->getPaymentIntentId());
                $refundRequest->setAmount((float) $order->getTotalAmount());
                
                $this->billingClient->createRefund($refundRequest);
            } catch (\Throwable $e) {
                return $this->json([
                    'error' => [
                        'code' => 'REFUND_FAILED',
                        'message' => 'Could not process refund',
                        'traceId' => $traceId,
                    ]
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }
        }
        
        // Release reservation
        if ($order->getReservationId()) {
            try {
                $this->inventoryClient->releaseReservation($order->getReservationId());
            } catch (\Throwable $e) {
                // Log but continue
            }
        }
        
        $order->setStatus(Order::STATUS_CANCELED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        return $this->json($this->serializeOrder($order));
    }

    #[Route('/{id}/events/payment-captured', name: 'order_payment_captured', methods: ['POST'])]
    #[OA\Post(path: '/v1/orders/{id}/events/payment-captured', operationId: 'orderPaymentCaptured', summary: 'Payment captured event', description: 'Webhook for payment captured event - commits inventory and marks order as paid')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Order ID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Event processed')]
    #[OA\Response(response: 404, description: 'Order not found')]
    #[OA\Response(response: 409, description: 'Order in invalid state')]
    public function paymentCaptured(string $id, Request $request): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        $traceId = uniqid('trace_');
        
        if (!$order) {
            return $this->json([
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => "Order {$id} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($order->getStatus() !== Order::STATUS_RESERVED) {
            return $this->json([
                'error' => [
                    'code' => 'ORDER_INVALID_STATE',
                    'message' => "Order is in {$order->getStatus()} state, expected reserved",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_CONFLICT);
        }
        
        // Commit inventory reservation
        if ($order->getReservationId()) {
            try {
                $this->inventoryClient->commitReservation($order->getReservationId());
            } catch (\Throwable $e) {
                // Log but continue - payment already captured
            }
        }
        
        $order->setStatus(Order::STATUS_PAID);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        return $this->json([
            'orderId' => $order->getId(),
            'status' => $order->getStatus(),
            'traceId' => $traceId,
        ]);
    }

    #[Route('/{id}/events/refunded', name: 'order_refunded', methods: ['POST'])]
    #[OA\Post(path: '/v1/orders/{id}/events/refunded', operationId: 'orderRefunded', summary: 'Refunded event', description: 'Webhook for refund event - marks order as canceled')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Order ID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Event processed')]
    #[OA\Response(response: 404, description: 'Order not found')]
    public function orderRefunded(string $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        $traceId = uniqid('trace_');
        
        if (!$order) {
            return $this->json([
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => "Order {$id} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        $order->setStatus(Order::STATUS_CANCELED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        return $this->json([
            'orderId' => $order->getId(),
            'status' => $order->getStatus(),
            'traceId' => $traceId,
        ]);
    }

    private function serializeOrder(Order $order): array
    {
        return [
            'orderId' => $order->getId(),
            'customerId' => $order->getCustomerId(),
            'customerName' => $order->getCustomerName(),
            'items' => $order->getItems(),
            'totalAmount' => $order->getTotalAmount(),
            'status' => $order->getStatus(),
            'reservationId' => $order->getReservationId(),
            'paymentIntentId' => $order->getPaymentIntentId(),
            'createdAt' => $order->getCreatedAt()->format('c'),
            'updatedAt' => $order->getUpdatedAt()->format('c'),
            'traceId' => uniqid('trace_'),
        ];
    }
}
