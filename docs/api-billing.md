# BillingService API Design

## Purpose
Create and manage payment intents, capture payments, and process refunds. Notifies OrderService of payment events.

## Responsibilities
- Payment intent creation and capture.
- Refunds for canceled/returned orders.
- Webhook delivery to OrderService.

## Endpoints
- POST /v1/billing/payment-intents
- GET /v1/billing/payment-intents/{id}
- POST /v1/billing/payments/{paymentId}/capture
- POST /v1/billing/refunds

### Sample: POST /v1/billing/payment-intents
Request:
```json
{
  "orderId": "ord_001",
  "amount": 20.00,
  "currency": "USD",
  "idempotencyKey": "pi_a4f7..."
}
```
Response:
```json
{
  "paymentIntentId": "pi_909",
  "status": "requires_payment_method",
  "clientSecret": "secret_...",
  "traceId": "..."
}
```

## Outbound Calls
- On capture: call OrderService webhook `POST /v1/orders/{id}/events/payment-captured`.
- On refund: optionally call OrderService `POST /v1/orders/{id}/events/refunded`.

## Authentication
- Client: PCI considerations; tokenized payment methods.
- Service: webhook signing (HMAC-SHA256), mTLS optional.

## Errors
- `PAYMENT_INTENT_FAILED`, `CAPTURE_FAILED`, `REFUND_FAILED`.

## Observability
- Trace payment lifecycle; include `orderId`, `paymentIntentId`.