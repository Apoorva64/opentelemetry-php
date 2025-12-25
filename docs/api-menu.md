# MenuService API Design

## Purpose
Authoritative catalog of menu items, categories, pricing, and availability signals consumed by OrderService and InventoryService.

## Responsibilities
- CRUD on items and categories.
- Price and availability validation for orders.
- Propagate availability changes to InventoryService.

## Endpoints
- GET /v1/menu/items
- GET /v1/menu/items/{itemId}
- POST /v1/menu/items
- PATCH /v1/menu/items/{itemId}
- POST /v1/menu/items/{itemId}/availability
- POST /v1/menu/validation (validate list of items and prices)

### Sample: POST /v1/menu/validation
Request:
```json
{
  "items": [
    {"itemId": "burger", "qty": 2, "unitPrice": 8.50},
    {"itemId": "fries", "qty": 1, "unitPrice": 3.00}
  ],
  "idempotencyKey": "a4f7..."
}
```
Response:
```json
{
  "valid": true,
  "validatedItems": [
    {"itemId": "burger", "qty": 2, "unitPrice": 8.50, "currentPrice": 8.50, "available": true},
    {"itemId": "fries", "qty": 1, "unitPrice": 3.00, "currentPrice": 3.00, "available": true}
  ],
  "traceId": "..."
}
```

## Outbound Calls (Inter-Service)
- On `POST /v1/menu/items/{itemId}/availability`, call InventoryService `POST /v1/inventory/reconcile` with affected SKUs.

## Authentication
- Service-to-service: mTLS or HMAC `X-Signature`.
- Client auth: Bearer tokens (restaurant staff portal or POS).

## Errors
- `ITEM_NOT_FOUND`, `ITEM_UNAVAILABLE`, `PRICE_MISMATCH`, `VALIDATION_FAILED`.

## Observability
- Include `traceparent` header; log `traceId`, `idempotencyKey`, itemIds.