# InventoryService API Design

## Purpose
Manages ingredient stock and reservations tied to orders; informs MenuService availability.

## Responsibilities
- Track stock levels per SKU/ingredient.
- Handle reservation lifecycle: create, commit, release.
- Reconcile availability with MenuService.

## Endpoints
- POST /v1/inventory/reservations
- POST /v1/inventory/reservations/{reservationId}/commit
- POST /v1/inventory/reservations/{reservationId}/release
- POST /v1/inventory/reconcile

### Sample: POST /v1/inventory/reservations
Request:
```json
{
  "orderId": "ord_001",
  "items": [
    {"itemId": "burger", "qty": 2},
    {"itemId": "fries", "qty": 1}
  ],
  "idempotencyKey": "res_a4f7..."
}
```
Response:
```json
{
  "reservationId": "res_77",
  "status": "reserved",
  "expiresAt": "2025-12-25T12:00:00Z",
  "traceId": "..."
}
```

## Outbound Calls
- On reservation failure: call OrderService `POST /v1/orders/{orderId}/events/reservation-failed` (optional enhancement).
- On reconcile: call MenuService to update item availability indicators.

## Authentication
- Service-to-service: mTLS or HMAC.

## Errors
- `RESERVATION_CONFLICT`, `INSUFFICIENT_STOCK`, `RESERVATION_NOT_FOUND`.

## Observability
- Emit metrics for reserve/commit/release; correlate by `orderId` and `reservationId`.