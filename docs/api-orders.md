# OrderService API Design

## Purpose
Primary orchestrator of customer orders. Validates items with MenuService, reserves stock with InventoryService, and creates payments with BillingService.

## Responsibilities
- Create/update/cancel orders.
- Coordinate inter-service calls for reservation and payment.
- Handle webhooks/events from BillingService.

## Endpoints
- POST /v1/orders
- GET /v1/orders/{orderId}
- POST /v1/orders/{orderId}/cancel
- POST /v1/orders/{orderId}/pay-intent
- POST /v1/orders/{orderId}/events/payment-captured (webhook)

### Sample: POST /v1/orders
Request:
```json
{
  "customer": {"id": "cust_123", "name": "Alex"},
  "items": [
    {"itemId": "burger", "qty": 2, "unitPrice": 8.50},
    {"itemId": "fries", "qty": 1, "unitPrice": 3.00}
  ],
  "idempotencyKey": "ord_a4f7..."
}
```
Response:
```json
{
  "orderId": "ord_001",
  "status": "reserved",
  "reservationId": "res_77",
  "paymentIntentId": "pi_909",
  "traceId": "..."
}
```

## Orchestration (Outbound Calls)
1) Validate items: call MenuService `POST /v1/menu/validation`.
2) Reserve ingredients: call InventoryService `POST /v1/inventory/reservations`.
3) Create payment intent: call BillingService `POST /v1/billing/payment-intents`.
4) On webhook `payment-captured` from BillingService: call InventoryService `POST /v1/inventory/reservations/{id}/commit`.

## Cancellation Flow
- If `status in [validating, reserved]`: release reservation `POST /v1/inventory/reservations/{id}/release`.
- If `status=paid`: call BillingService refund, then release reservation.

## Authentication
- Client: Bearer tokens (customer app/POS).
- Service: mTLS/HMAC; webhook signature verification from BillingService.

## Errors
- `MENU_VALIDATION_FAILED`, `INVENTORY_RESERVE_FAILED`, `PAYMENT_INTENT_FAILED`, `ORDER_NOT_FOUND`, `ORDER_INVALID_STATE`.

## Observability
- Propagate `traceparent`; include `reservationId`, `paymentIntentId` in logs.