# K6 Load Testing for Restaurant Management System

## Prerequisites

Install k6:
- **macOS**: `brew install k6`
- **Windows**: `choco install k6` or `winget install k6`
- **Linux**: See https://k6.io/docs/get-started/installation/
- **Docker**: `docker pull grafana/k6`

## Test Files

| File | Description |
|------|-------------|
| `smoke-test.js` | Quick single-iteration test to verify system works |
| `load-test.js` | Full load test with multiple scenarios |

## Running Tests

### Quick Smoke Test

Verify the system is working:

```bash
# Local (services running on localhost)
k6 run tests/smoke-test.js

# Against Docker services
k6 run \
  -e MENU_URL=http://localhost:8000 \
  -e ORDERS_URL=http://localhost:8001 \
  -e INVENTORY_URL=http://localhost:8002 \
  -e BILLING_URL=http://localhost:8003 \
  tests/smoke-test.js

# Using Docker
docker run --rm -i --network=host grafana/k6 run - < tests/smoke-test.js
```

### Load Test Scenarios

The full load test includes multiple scenarios:

1. **Smoke** (0-30s): 1 VU constant - verify basic functionality
2. **Load** (35s-9m): 0→10→20→0 VUs - normal traffic simulation
3. **Stress** (10m-26m): 0→50→100→0 VUs - find breaking points
4. **Spike** (27m-28m): 0→100→0 VUs sudden - test sudden traffic

```bash
# Run all scenarios (full ~28 minute test)
k6 run tests/load-test.js

# Run only smoke scenario
k6 run --scenario smoke tests/load-test.js

# Run with custom duration/VUs
k6 run --vus 10 --duration 1m tests/load-test.js

# Run with output to file
k6 run --out json=results.json tests/load-test.js

# Run with summary export
k6 run --summary-export=summary.json tests/load-test.js
```

### Environment Variables

Configure service URLs with environment variables:

```bash
export MENU_URL=http://menu:8000
export ORDERS_URL=http://orders:8001
export INVENTORY_URL=http://inventory:8002
export BILLING_URL=http://billing:8003

k6 run tests/load-test.js
```

## Thresholds

The tests define pass/fail thresholds:

| Metric | Threshold |
|--------|-----------|
| `http_req_duration` | p95 < 500ms, p99 < 1000ms |
| `http_req_failed` | < 5% failure rate |
| `errors` | < 10% error rate |
| `order_flow_duration` | p95 < 2000ms |

## Custom Metrics

The load test tracks these custom metrics:

- `orders_created` - Counter of successfully created orders
- `payments_captured` - Counter of successfully captured payments
- `errors` - Rate of errors across all operations
- `order_flow_duration` - Trend of complete order flow time

## Sample Output

```
          /\      |‾‾| /‾‾/   /‾‾/   
     /\  /  \     |  |/  /   /  /    
    /  \/    \    |     (   /   ‾‾\  
   /          \   |  |\  \ |  (‾)  | 
  / __________ \  |__| \__\ \_____/ .io

     execution: local
     scenarios: (100.00%) 1 scenario, 10 max VUs, 1m30s max duration

  ✓ menu health status 200
  ✓ orders health status 200
  ✓ inventory health status 200
  ✓ billing health status 200
  ✓ order created status 2xx
  ✓ payment captured status 2xx

     checks.........................: 100.00% ✓ 240  ✗ 0
     data_received..................: 125 kB  2.1 kB/s
     data_sent......................: 45 kB   750 B/s
     http_req_duration..............: avg=45.2ms p(95)=89.3ms p(99)=125.1ms
     orders_created.................: 40      0.67/s
     payments_captured..............: 38      0.63/s
```

## Running with Docker Compose

Add k6 service to docker-compose.yml:

```yaml
  k6:
    image: grafana/k6
    volumes:
      - ./tests:/tests
    environment:
      - MENU_URL=http://menu:8000
      - ORDERS_URL=http://orders:8001
      - INVENTORY_URL=http://inventory:8002
      - BILLING_URL=http://billing:8003
    command: run /tests/smoke-test.js
    profiles:
      - loadtest
    depends_on:
      menu:
        condition: service_healthy
      orders:
        condition: service_healthy
      inventory:
        condition: service_healthy
      billing:
        condition: service_healthy
```

Then run:

```bash
docker compose --profile loadtest up k6
```
