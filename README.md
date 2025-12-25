# Restaurant Management System (RMS)

A microservices-based restaurant management system with four Symfony APIs that communicate with each other:

| Service | Port | Database | Description |
|---------|------|----------|-------------|
| **MenuService** | 8000 | SQLite | Menu items, pricing, availability |
| **OrderService** | 8001 | SQLite | Order lifecycle, orchestration |
| **InventoryService** | 8002 | SQLite | Stock, reservations |
| **BillingService** | 8003 | SQLite | Payments, refunds |

## Inter-Service Call Flow

```
                    ┌──────────────┐
                    │   Client     │
                    └──────┬───────┘
                           │ POST /v1/orders
                           ▼
                    ┌──────────────┐
              ┌─────│ OrderService │─────┐
              │     └──────┬───────┘     │
              │            │             │
    ①validate│            │             │③payment-intent
              ▼            │             ▼
       ┌────────────┐      │      ┌──────────────┐
       │MenuService │      │      │BillingService│
       └────────────┘      │      └──────┬───────┘
                           │             │
                  ②reserve │             │④webhook
                           ▼             │
                  ┌────────────────┐     │
                  │InventoryService│◄────┘
                  └────────────────┘
```

## Quick Start (WSL)

### 1. Start all services (in separate WSL terminals)

```bash
# Terminal 1 - Menu Service (port 8000)
cd /mnt/c/Users/appad/otel-php/services/menu
php -S localhost:8000 -t public

# Terminal 2 - Orders Service (port 8001)
cd /mnt/c/Users/appad/otel-php/services/orders
php -S localhost:8001 -t public

# Terminal 3 - Inventory Service (port 8002)
cd /mnt/c/Users/appad/otel-php/services/inventory
php -S localhost:8002 -t public

# Terminal 4 - Billing Service (port 8003)
cd /mnt/c/Users/appad/otel-php/services/billing
php -S localhost:8003 -t public
```

### 2. Test the flow

```bash
# Create a menu item
curl -X POST http://localhost:8000/v1/menu/items \
  -H "Content-Type: application/json" \
  -d '{"name":"Burger","price":"8.50","category":"main","available":true}'

# Create an order (calls Menu → Inventory → Billing)
curl -X POST http://localhost:8001/v1/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customer": {"id": "cust_1", "name": "John"},
    "items": [{"itemId": "<ITEM_ID>", "qty": 2, "unitPrice": 8.50}],
    "idempotencyKey": "order_001"
  }'

# Capture payment (Billing → Orders webhook)
curl -X POST http://localhost:8003/v1/billing/payments/<PAYMENT_INTENT_ID>/capture

# Check order status
curl http://localhost:8001/v1/orders/<ORDER_ID>
```

## Documentation

- [docs/overview.md](docs/overview.md) — Architecture and flows
- [docs/api-menu.md](docs/api-menu.md) — MenuService API
- [docs/api-orders.md](docs/api-orders.md) — OrderService API
- [docs/api-inventory.md](docs/api-inventory.md) — InventoryService API
- [docs/api-billing.md](docs/api-billing.md) — BillingService API

## Project Structure

```
services/
├── menu/           # Symfony 8 - MenuService
├── orders/         # Symfony 8 - OrderService  
├── inventory/      # Symfony 8 - InventoryService
└── billing/        # Symfony 8 - BillingService

docs/
├── overview.md     # Architecture overview
├── api-menu.md
├── api-orders.md
├── api-inventory.md
└── api-billing.md
```

## Database Reset

To reset databases for all services:

```bash
cd /mnt/c/Users/appad/otel-php/services/menu && php bin/console doctrine:schema:drop --force && php bin/console doctrine:schema:create
cd /mnt/c/Users/appad/otel-php/services/orders && php bin/console doctrine:schema:drop --force && php bin/console doctrine:schema:create
cd /mnt/c/Users/appad/otel-php/services/inventory && php bin/console doctrine:schema:drop --force && php bin/console doctrine:schema:create
cd /mnt/c/Users/appad/otel-php/services/billing && php bin/console doctrine:schema:drop --force && php bin/console doctrine:schema:create
```