# RMS Architecture Overview

## Components
- MenuService: Authoritative catalog of sellable items, prices, and availability.
- OrderService: Orchestrates order lifecycle; validates items, reserves inventory, creates payments, and finalizes.
- InventoryService: Manages ingredient stock; reserves/commits/releases against orders; maintains availability signals.
- BillingService: Manages payment intents, captures, refunds; calls back OrderService on payment state changes.

## Service-to-Service Auth
- Option A: mTLS between services within the mesh.
- Option B: HMAC with shared service keys. Header: `X-Signature` over canonical request.
- Option C: OAuth2 client credentials (per-service clients) with short-lived tokens.

## Canonical Error Shape
```json
{
  "error": {
    "code": "string",           // machine-readable
    "message": "string",         // human-readable
    "details": {"...": "..."}, // optional structured context
    "traceId": "uuid"            // for correlation
  }
}
```

## Core Flows

### Order Creation + Payment
1) OrderService receives `POST /orders`.
2) Calls MenuService to validate each item+price (idempotent).
3) Creates provisional order `status=validating`.
4) Calls InventoryService `POST /inventory/reservations` to reserve ingredients; gets `reservationId`.
5) Updates order `status=reserved` with `reservationId`.
6) Calls BillingService `POST /billing/payment-intents` to create intent; stores `paymentIntentId`.
7) Client pays; BillingService captures.
8) BillingService calls OrderService webhook `POST /orders/{id}/events/payment-captured`.
9) OrderService calls InventoryService `POST /inventory/reservations/{id}/commit` and sets `status=paid`.

### Order Cancellation
- If unpaid: OrderService releases reservation immediately and sets `status=canceled`.
- If paid: OrderService calls BillingService for refund; then releases inventory.

### Menu Availability Update
- MenuService receives update (e.g., item marked unavailable).
- MenuService calls InventoryService reconcile endpoint; Inventory computes availability from stock.
- Optional: Inventory calls OrderService to flag at-risk provisional orders.

## Non-Functional Requirements
- Reliability: retries with exponential backoff; idempotency keys on POSTs.
- Observability: trace propagation (`traceparent`), structured logs, metrics per endpoint.
- Performance: p95<150ms for single-service calls; p95<600ms for orchestrated flows.
- Versioning: `/v1` base path; backward-compatible changes preferred; use additive fields.
- Rate Limits: per-client and per-service; 429 returns `Retry-After`.

## Ports & Endpoints (local dev suggestion)
- MenuService: `http://localhost:3001`
- OrderService: `http://localhost:3002`
- InventoryService: `http://localhost:3003`
- BillingService: `http://localhost:3004`