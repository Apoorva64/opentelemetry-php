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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use OpenApi\Attributes as OA;

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
    public function __construct(
        private EntityManagerInterface $em,
        private PaymentIntentRepository $paymentIntentRepository,
        private RefundRepository $refundRepository,
        private HttpClientInterface $httpClient,
    ) {}

    #[Route('/health', name: 'billing_health', methods: ['GET'])]
    #[OA\Get(path: '/v1/billing/health', summary: 'Health check', description: 'Returns the health status of the billing service')]
    #[OA\Response(response: 200, description: 'Service is healthy')]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok', 'service' => 'billing']);
    }

    #[Route('/payment-intents', name: 'billing_create_intent', methods: ['POST'])]
    #[OA\Post(path: '/v1/billing/payment-intents', summary: 'Create payment intent', description: 'Creates a new payment intent for an order')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['orderId', 'amount'],
            properties: [
                new OA\Property(property: 'idempotencyKey', type: 'string'),
                new OA\Property(property: 'orderId', type: 'string', example: 'order-123'),
                new OA\Property(property: 'amount', type: 'number', example: 25.99),
                new OA\Property(property: 'currency', type: 'string', example: 'USD')
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Payment intent created')]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $traceId = uniqid('trace_');
        $idempotencyKey = $data['idempotencyKey'] ?? null;
        
        // Check for idempotent request
        if ($idempotencyKey) {
            $existing = $this->paymentIntentRepository->findByIdempotencyKey($idempotencyKey);
            if ($existing) {
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
        
        return $this->json([
            'paymentIntentId' => $paymentIntent->getId(),
            'status' => $paymentIntent->getStatus(),
            'clientSecret' => $paymentIntent->getClientSecret(),
            'traceId' => $traceId,
        ], Response::HTTP_CREATED);
    }

    #[Route('/payment-intents/{id}', name: 'billing_get_intent', methods: ['GET'])]
    #[OA\Get(path: '/v1/billing/payment-intents/{id}', summary: 'Get payment intent', description: 'Retrieves payment intent details')]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Payment intent ID', required: true)]
    #[OA\Response(response: 200, description: 'Payment intent found')]
    #[OA\Response(response: 404, description: 'Payment intent not found')]
    public function getPaymentIntent(string $id): JsonResponse
    {
        $paymentIntent = $this->paymentIntentRepository->find($id);
        $traceId = uniqid('trace_');
        
        if (!$paymentIntent) {
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
    #[OA\Post(path: '/v1/billing/payments/{paymentId}/capture', summary: 'Capture payment', description: 'Captures a payment and notifies the order service')]
    #[OA\Parameter(name: 'paymentId', in: 'path', description: 'Payment intent ID', required: true)]
    #[OA\Response(response: 200, description: 'Payment captured')]
    #[OA\Response(response: 404, description: 'Payment intent not found')]
    public function capturePayment(string $paymentId): JsonResponse
    {
        $paymentIntent = $this->paymentIntentRepository->find($paymentId);
        $traceId = uniqid('trace_');
        
        if (!$paymentIntent) {
            return $this->json([
                'error' => [
                    'code' => 'PAYMENT_INTENT_NOT_FOUND',
                    'message' => "Payment intent {$paymentId} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($paymentIntent->getStatus() === PaymentIntent::STATUS_CAPTURED) {
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
        
        // Call OrderService webhook to notify payment captured
        $ordersUrl = $_ENV['ORDERS_SERVICE_URL'] ?? 'http://localhost:8001';
        try {
            $this->httpClient->request('POST', "{$ordersUrl}/v1/orders/{$paymentIntent->getOrderId()}/events/payment-captured", [
                'json' => [
                    'paymentIntentId' => $paymentIntent->getId(),
                    'amount' => $paymentIntent->getAmount(),
                    'currency' => $paymentIntent->getCurrency(),
                ],
            ]);
        } catch (\Throwable $e) {
            // Log but don't fail - payment is already captured
        }
        
        return $this->json([
            'paymentIntentId' => $paymentIntent->getId(),
            'status' => $paymentIntent->getStatus(),
            'traceId' => $traceId,
        ]);
    }

    #[Route('/refunds', name: 'billing_refund', methods: ['POST'])]
    #[OA\Post(path: '/v1/billing/refunds', summary: 'Create refund', description: 'Creates a refund for a captured payment')]
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
        
        $paymentIntent = $this->paymentIntentRepository->find($paymentIntentId);
        
        if (!$paymentIntent) {
            return $this->json([
                'error' => [
                    'code' => 'PAYMENT_INTENT_NOT_FOUND',
                    'message' => "Payment intent {$paymentIntentId} not found",
                    'traceId' => $traceId,
                ]
            ], Response::HTTP_NOT_FOUND);
        }
        
        if ($paymentIntent->getStatus() !== PaymentIntent::STATUS_CAPTURED) {
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
        
        // Call OrderService webhook to notify refund
        $ordersUrl = $_ENV['ORDERS_SERVICE_URL'] ?? 'http://localhost:8001';
        try {
            $this->httpClient->request('POST', "{$ordersUrl}/v1/orders/{$orderId}/events/refunded", [
                'json' => [
                    'refundId' => $refund->getId(),
                    'paymentIntentId' => $paymentIntentId,
                    'amount' => $refund->getAmount(),
                ],
            ]);
        } catch (\Throwable $e) {
            // Log but don't fail
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
