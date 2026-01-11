<?php

namespace App\Controller;

use App\Entity\PaymentIntent;
use App\Entity\Refund;
use App\Repository\PaymentIntentRepository;
use App\Repository\RefundRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use OpenApi\Attributes as OA;
use OrdersApi\OrdersClient\DefaultApi as OrdersClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use App\Middleware\OpenTelemetryGuzzleMiddleware;

#[OA\Info(
    version: '1.0.0',
    title: 'Billing Service API',
    description: 'Restaurant payment processing and refunds'
)]
#[OA\Server(url: 'http://localhost:8003', description: 'Billing Service')]
#[Route('/v1/billing')]
#[OA\Tag(name: 'Billing', description: 'Payment processing and refunds')]
class BillingController extends AbstractController
{
    private OrdersClient $ordersClient;

    public function __construct(
        private EntityManagerInterface $em,
        private PaymentIntentRepository $paymentIntentRepository,
        private RefundRepository $refundRepository,
        private LoggerInterface $logger,
    ) {
        $stack = HandlerStack::create();
        $stack->push(new OpenTelemetryGuzzleMiddleware(), 'otel_attributes');
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $ordersConfig = new \OrdersApi\Configuration();
        $ordersConfig->setHost($_ENV['ORDERS_SERVICE_URL'] ?? 'http://localhost:8001');
        $this->ordersClient = new OrdersClient($guzzle, $ordersConfig);
        
        $this->logger->debug('BillingController initialized', [
            'ordersServiceUrl' => $_ENV['ORDERS_SERVICE_URL'] ?? 'http://localhost:8001'
        ]);
    }

    #[Route('/health', name: 'billing_health', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/billing/health',
        operationId: 'getBillingHealth',
        summary: 'Health check',
        description: 'Returns the health status of the billing service'
    )]
    #[OA\Response(
        response: 200,
        description: 'Service is healthy',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ok'),
                new OA\Property(property: 'service', type: 'string', example: 'billing')
            ]
        )
    )]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok', 'service' => 'billing']);
    }

    #[Route('/payment-intents', name: 'billing_create_intent', methods: ['POST'])]
    #[OA\Post(
        path: '/v1/billing/payment-intents',
        operationId: 'createPaymentIntent',
        summary: 'Create payment intent',
        description: 'Creates a new payment intent for an order'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['orderId', 'amount'],
            properties: [
                new OA\Property(property: 'idempotencyKey', type: 'string', example: 'payment-123-abc'),
                new OA\Property(property: 'orderId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                new OA\Property(property: 'amount', type: 'number', format: 'float', example: 25.99),
                new OA\Property(property: 'currency', type: 'string', example: 'USD')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Payment intent created',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'paymentIntentId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                new OA\Property(property: 'status', type: 'string', example: 'requires_payment_method'),
                new OA\Property(property: 'clientSecret', type: 'string', example: 'secret_abc123'),
                new OA\Property(property: 'traceId', type: 'string', example: 'trace_abc123')
            ]
        )
    )]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $traceId = uniqid('trace_');
        $idempotencyKey = $data['idempotencyKey'] ?? null;
        
        $this->logger->info('Creating payment intent', [
            'orderId' => $data['orderId'] ?? 'unknown',
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'idempotencyKey' => $idempotencyKey,
            'traceId' => $traceId
        ]);
        
        // Check for idempotent request
        if ($idempotencyKey) {
            $existing = $this->paymentIntentRepository->findByIdempotencyKey($idempotencyKey);
            if ($existing) {
                $this->logger->info('Returning existing payment intent (idempotent)', [
                    'paymentIntentId' => $existing->getId(),
                    'idempotencyKey' => $idempotencyKey
                ]);
                return $this->json([
                    'paymentIntentId' => $existing->getId(),
                    'status' => $existing->getStatus(),
                    'clientSecret' => $existing->getClientSecret(),
                    'traceId' => $traceId,
                ]);
            }
        }
        
        $orderId = $data['orderId'] ?? '';
        $amount = $data['amount'] ?? 0;
        $currency = $data['currency'] ?? 'USD';
        
        $paymentIntent = new PaymentIntent();
        $paymentIntent->setOrderId($orderId)
            ->setAmount(number_format((float) $amount, 2, '.', ''))
            ->setCurrency(strtoupper($currency))
            ->setStatus(PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD)
            ->setIdempotencyKey($idempotencyKey);
        
        $this->em->persist($paymentIntent);
        $this->em->flush();
        
        $this->logger->info('Payment intent created successfully', [
            'paymentIntentId' => $paymentIntent->getId(),
            'orderId' => $orderId,
            'amount' => $amount,
            'status' => $paymentIntent->getStatus(),
            'traceId' => $traceId
        ]);
        
        return $this->json([
            'paymentIntentId' => $paymentIntent->getId(),
            'status' => $paymentIntent->getStatus(),
            'clientSecret' => $paymentIntent->getClientSecret(),
            'traceId' => $traceId,
        ], Response::HTTP_CREATED);
    }

    #[Route('/payment-intents/{id}', name: 'billing_get_intent', methods: ['GET'])]
    #[OA\Get(
        path: '/v1/billing/payment-intents/{id}',
        operationId: 'getPaymentIntent',
        summary: 'Get payment intent',
        description: 'Retrieves payment intent details'
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Payment intent ID',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment intent found',
        content: new OA\JsonContent(ref: '#/components/schemas/PaymentIntent')
    )]
    #[OA\Response(
        response: 404,
        description: 'Payment intent not found',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'string', example: 'PAYMENT_INTENT_NOT_FOUND'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'traceId', type: 'string')
                    ]
                )
            ]
        )
    )]
    public function getPaymentIntent(string $id): JsonResponse
    {
        $paymentIntent = $this->paymentIntentRepository->find($id);
        $traceId = uniqid('trace_');
        
        $this->logger->info('Fetching payment intent', ['paymentIntentId' => $id, 'traceId' => $traceId]);
        
        if (!$paymentIntent) {
            $this->logger->warning('Payment intent not found', ['paymentIntentId' => $id]);
            return $this->json([
                'error' => [
                    'code' => 'PAYMENT_INTENT_NOT_FOUND',
                    'message' => "Payment intent {$id} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        return $this->json([
            'paymentIntentId' => $paymentIntent->getId(),
            'orderId' => $paymentIntent->getOrderId(),
            'amount' => $paymentIntent->getAmount(),
            'currency' => $paymentIntent->getCurrency(),
            'status' => $paymentIntent->getStatus(),
            'createdAt' => $paymentIntent->getCreatedAt()->format('c'),
            'traceId' => $traceId,
        ]);
    }

    #[Route('/payments/{paymentId}/capture', name: 'billing_capture', methods: ['POST'])]
    #[OA\Post(path: '/v1/billing/payments/{paymentId}/capture', operationId: 'capturePayment', summary: 'Capture payment', description: 'Captures a payment and notifies the order service')]
    #[OA\Parameter(name: 'paymentId', in: 'path', description: 'Payment intent ID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Payment captured')]
    #[OA\Response(response: 404, description: 'Payment intent not found')]
    public function capturePayment(string $paymentId): JsonResponse
    {
        $paymentIntent = $this->paymentIntentRepository->find($paymentId);
        $traceId = uniqid('trace_');
        
        $this->logger->info('Capturing payment', ['paymentIntentId' => $paymentId, 'traceId' => $traceId]);
        
        if (!$paymentIntent) {
            $this->logger->warning('Payment intent not found for capture', ['paymentIntentId' => $paymentId]);
            return $this->json([
                'error' => [
                    'code' => 'PAYMENT_INTENT_NOT_FOUND',
                    'message' => "Payment intent {$paymentId} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($paymentIntent->getStatus() === PaymentIntent::STATUS_CAPTURED) {
            $this->logger->info('Payment already captured', ['paymentIntentId' => $paymentId]);
            return $this->json([
                'paymentIntentId' => $paymentIntent->getId(),
                'status' => $paymentIntent->getStatus(),
                'traceId' => $traceId,
            ]);
        }
        
        // Simulate payment capture (in real life, call payment processor)
        $paymentIntent->setStatus(PaymentIntent::STATUS_CAPTURED);
        $paymentIntent->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        $this->logger->info('Payment captured successfully', [
            'paymentIntentId' => $paymentId,
            'orderId' => $paymentIntent->getOrderId(),
            'amount' => $paymentIntent->getAmount(),
            'traceId' => $traceId
        ]);
        
        // Call OrderService webhook to notify payment captured
        $this->logger->info('Notifying order service of payment capture', [
            'orderId' => $paymentIntent->getOrderId()
        ]);
        try {
            $this->ordersClient->orderPaymentCaptured($paymentIntent->getOrderId());
            $this->logger->info('Order service notified successfully');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to notify order service of payment capture', [
                'orderId' => $paymentIntent->getOrderId(),
                'error' => $e->getMessage()
            ]);
        }
        
        return $this->json([
            'paymentIntentId' => $paymentIntent->getId(),
            'status' => $paymentIntent->getStatus(),
            'traceId' => $traceId,
        ]);
    }

    #[Route('/refunds', name: 'billing_refund', methods: ['POST'])]
    #[OA\Post(path: '/v1/billing/refunds', operationId: 'createRefund', summary: 'Create refund', description: 'Creates a refund for a captured payment')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['orderId', 'paymentIntentId', 'amount'],
            properties: [
                new OA\Property(property: 'orderId', type: 'string'),
                new OA\Property(property: 'paymentIntentId', type: 'string'),
                new OA\Property(property: 'amount', type: 'number', example: 25.99)
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Refund created')]
    #[OA\Response(response: 400, description: 'Cannot refund - invalid payment status')]
    #[OA\Response(response: 404, description: 'Payment intent not found')]
    public function createRefund(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $traceId = uniqid('trace_');
        
        $orderId = $data['orderId'] ?? '';
        $paymentIntentId = $data['paymentIntentId'] ?? '';
        $amount = $data['amount'] ?? 0;
        
        $this->logger->info('Creating refund', [
            'orderId' => $orderId,
            'paymentIntentId' => $paymentIntentId,
            'amount' => $amount,
            'traceId' => $traceId
        ]);
        
        $paymentIntent = $this->paymentIntentRepository->find($paymentIntentId);
        
        if (!$paymentIntent) {
            $this->logger->warning('Payment intent not found for refund', ['paymentIntentId' => $paymentIntentId]);
            return $this->json([
                'error' => [
                    'code' => 'PAYMENT_INTENT_NOT_FOUND',
                    'message' => "Payment intent {$paymentIntentId} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($paymentIntent->getStatus() !== PaymentIntent::STATUS_CAPTURED) {
            $this->logger->warning('Cannot refund payment - invalid status', [
                'paymentIntentId' => $paymentIntentId,
                'currentStatus' => $paymentIntent->getStatus()
            ]);
            return $this->json([
                'error' => [
                    'code' => 'REFUND_FAILED',
                    'message' => "Cannot refund payment in {$paymentIntent->getStatus()} status",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Create refund record
        $refund = new Refund();
        $refund->setOrderId($orderId)
            ->setPaymentIntentId($paymentIntentId)
            ->setAmount(number_format((float) $amount, 2, '.', ''))
            ->setStatus(Refund::STATUS_COMPLETED);
        
        $this->em->persist($refund);
        
        // Update payment intent status
        $paymentIntent->setStatus(PaymentIntent::STATUS_REFUNDED);
        $paymentIntent->setUpdatedAt(new \DateTimeImmutable());
        
        $this->em->flush();
        
        $this->logger->info('Refund created successfully', [
            'refundId' => $refund->getId(),
            'orderId' => $orderId,
            'paymentIntentId' => $paymentIntentId,
            'amount' => $refund->getAmount(),
            'traceId' => $traceId
        ]);
        
        // Call OrderService webhook to notify refund
        $this->logger->info('Notifying order service of refund', ['orderId' => $orderId]);
        try {
            $this->ordersClient->orderRefunded($orderId);
            $this->logger->info('Order service notified of refund successfully');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to notify order service of refund', [
                'orderId' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
        
        return $this->json([
            'refundId' => $refund->getId(),
            'paymentIntentId' => $paymentIntentId,
            'orderId' => $orderId,
            'amount' => $refund->getAmount(),
            'status' => $refund->getStatus(),
            'traceId' => $traceId,
        ], Response::HTTP_CREATED);
    }
}
