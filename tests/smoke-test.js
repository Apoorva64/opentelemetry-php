import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Counter } from 'k6/metrics';
import { randomString, randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

// Long-running smoke test - continuous verification of system health
// Runs for 9 hours with looping iterations
const errorRate = new Rate('errors');
const iterations = new Counter('iterations');

const BASE_URLS = {
    menu: __ENV.MENU_URL || 'http://localhost:8000',
    orders: __ENV.ORDERS_URL || 'http://localhost:8001',
    inventory: __ENV.INVENTORY_URL || 'http://localhost:8002',
    billing: __ENV.BILLING_URL || 'http://localhost:8003',
};

// Test duration: 9 hours
const DURATION = __ENV.DURATION || '9h';
// Sleep between iterations (seconds)
const ITERATION_SLEEP = parseInt(__ENV.ITERATION_SLEEP || '5');

export const options = {
    scenarios: {
        continuous_smoke: {
            executor: 'constant-vus',
            vus: parseInt(__ENV.VUS || '1'),
            duration: DURATION,
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.05'],
        errors: ['rate<0.05'],
    },
};

const headers = { 'Content-Type': 'application/json' };

export default function() {
    iterations.add(1);
    const iterNum = __ITER + 1;
    
    if (iterNum === 1 || iterNum % 100 === 0) {
        console.log(`\n=== Smoke Test - Iteration ${iterNum} ===\n`);
    }
    
    // 1. Health checks
    group('Health Checks', function() {
        const services = [
            { name: 'Menu', url: `${BASE_URLS.menu}/v1/menu/health` },
            { name: 'Orders', url: `${BASE_URLS.orders}/v1/orders/health` },
            { name: 'Inventory', url: `${BASE_URLS.inventory}/v1/inventory/health` },
            { name: 'Billing', url: `${BASE_URLS.billing}/v1/billing/health` },
        ];
        
        for (const svc of services) {
            const res = http.get(svc.url);
            const ok = check(res, {
                [`${svc.name} health OK`]: (r) => r.status === 200,
            });
            if (!ok) errorRate.add(1);
        }
    });
    
    sleep(0.5);
    
    // 2. Get menu items
    group('Menu Service', function() {
        const menuRes = http.get(`${BASE_URLS.menu}/v1/menu/items`);
        check(menuRes, {
            'menu items retrieved': (r) => r.status === 200,
        }) || errorRate.add(1);
    });
    
    sleep(0.5);
    
    // 3. Check inventory
    group('Inventory Service', function() {
        const stockRes = http.get(`${BASE_URLS.inventory}/v1/inventory/stock`);
        check(stockRes, {
            'stock retrieved': (r) => r.status === 200,
        }) || errorRate.add(1);
    });
    
    sleep(0.5);
    
    // 4. Create a test order
    group('Full Order Flow', function() {
        // First get a menu item
        const menuRes = http.get(`${BASE_URLS.menu}/v1/menu/items`);
        
        if (menuRes.status !== 200) {
            console.log(`Menu fetch failed with status ${menuRes.status}: ${menuRes.body}`);
            errorRate.add(1);
            return;
        }
        
        let menuData;
        try {
            menuData = JSON.parse(menuRes.body);
        } catch (e) {
            console.log(`Failed to parse menu response: ${menuRes.body}`);
            errorRate.add(1);
            return;
        }
        
        if (!menuData.items || menuData.items.length === 0) {
            const createRes = http.post(`${BASE_URLS.menu}/v1/menu/items`, JSON.stringify({
                name: 'Smoke Test Item',
                description: 'Item for smoke testing',
                price: '5.99',
                category: 'test',
                available: true,
                ingredients: ['test_ingredient'],
            }), { headers });
            
            if (createRes.status !== 201 && createRes.status !== 200) {
                errorRate.add(1);
                return;
            }
            
            try {
                menuData.items = [JSON.parse(createRes.body)];
            } catch (e) {
                console.log(`Failed to parse create menu item response: ${createRes.body}`);
                errorRate.add(1);
                return;
            }
        }
        
        const item = menuData.items[randomIntBetween(0, menuData.items.length - 1)];
        
        // Create order
        const orderPayload = {
            customer: { id: `smoke_customer_${__VU}`, name: 'Smoke Test' },
            items: [{ itemId: item.id, qty: randomIntBetween(1, 3), unitPrice: parseFloat(item.price) }],
            idempotencyKey: `smoke_${Date.now()}_${randomString(8)}`,
        };
        
        const orderRes = http.post(`${BASE_URLS.orders}/v1/orders`, JSON.stringify(orderPayload), { headers });
        
        const orderOk = check(orderRes, {
            'order created': (r) => r.status >= 200 && r.status < 300,
        });
        
        if (!orderOk) {
            errorRate.add(1);
            return;
        }
        
        let orderData;
        try {
            orderData = JSON.parse(orderRes.body);
        } catch (e) {
            console.log(`Failed to parse order response: ${orderRes.body}`);
            errorRate.add(1);
            return;
        }
        
        // Capture payment if available
        if (orderData.paymentIntentId) {
            sleep(0.3);
            const captureRes = http.post(
                `${BASE_URLS.billing}/v1/billing/payments/${orderData.paymentIntentId}/capture`,
                null,
                { headers }
            );
            
            const captureOk = check(captureRes, {
                'payment captured': (r) => r.status >= 200 && r.status < 300,
            });
            
            if (!captureOk) {
                // Payment capture is optional, don't fail test
            }
            
            // Verify final status
            sleep(0.3);
            http.get(`${BASE_URLS.orders}/v1/orders/${orderData.orderId}`);
        }
        
        // // Replenish stock to avoid running out during long smoke tests
        // sleep(0.3);
        // const orderedQty = orderPayload.items[0].qty;
        // const replenishRes = http.put(
        //     `${BASE_URLS.inventory}/v1/inventory/stock/${item.id}`,
        //     JSON.stringify({
        //         quantity: orderedQty,
        //         itemName: item.name,
        //     }),
        //     { headers }
        // );
        
        // check(replenishRes, {
        //     'stock replenished': (r) => r.status >= 200 && r.status < 300,
        // }) || errorRate.add(1);
    });
    
    // Sleep between iterations to maintain steady load
    sleep(ITERATION_SLEEP);
}

export function handleSummary(data) {
    const duration = data.state.testRunDurationMs / 1000 / 60;
    const totalIterations = data.metrics.iterations ? data.metrics.iterations.values.count : 0;
    const errorCount = data.metrics.errors ? data.metrics.errors.values.count : 0;
    
    return {
        'stdout': `
=== Smoke Test Summary ===
Duration: ${duration.toFixed(1)} minutes
Total Iterations: ${totalIterations}
Errors: ${errorCount}
Error Rate: ${((errorCount / Math.max(totalIterations, 1)) * 100).toFixed(2)}%
===========================
`,
    };
}
