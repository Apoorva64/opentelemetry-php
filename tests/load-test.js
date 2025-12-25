import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { randomString, randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

// Custom metrics
const orderCreated = new Counter('orders_created');
const paymentCaptured = new Counter('payments_captured');
const errorRate = new Rate('errors');
const orderDuration = new Trend('order_flow_duration');

// Configuration
const BASE_URLS = {
    menu: __ENV.MENU_URL || 'http://localhost:8000',
    orders: __ENV.ORDERS_URL || 'http://localhost:8001',
    inventory: __ENV.INVENTORY_URL || 'http://localhost:8002',
    billing: __ENV.BILLING_URL || 'http://localhost:8003',
};

// Test scenarios
export const options = {
    scenarios: {
        // Smoke test - verify system works
        smoke: {
            executor: 'constant-vus',
            vus: 1,
            duration: '30s',
            startTime: '0s',
            tags: { scenario: 'smoke' },
        },
        // Load test - normal traffic
        load: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '1m', target: 10 },  // Ramp up
                { duration: '3m', target: 10 },  // Stay at 10 users
                { duration: '1m', target: 20 },  // Ramp to 20
                { duration: '3m', target: 20 },  // Stay at 20 users
                { duration: '1m', target: 0 },   // Ramp down
            ],
            startTime: '35s',
            tags: { scenario: 'load' },
        },
        // Stress test - find breaking point
        stress: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '2m', target: 50 },
                { duration: '5m', target: 50 },
                { duration: '2m', target: 100 },
                { duration: '5m', target: 100 },
                { duration: '2m', target: 0 },
            ],
            startTime: '10m',
            tags: { scenario: 'stress' },
        },
        // Spike test - sudden traffic
        spike: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '10s', target: 100 },
                { duration: '1m', target: 100 },
                { duration: '10s', target: 0 },
            ],
            startTime: '27m',
            tags: { scenario: 'spike' },
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1000'],
        http_req_failed: ['rate<0.05'],
        errors: ['rate<0.1'],
        order_flow_duration: ['p(95)<2000'],
    },
};

// Shared headers
const headers = {
    'Content-Type': 'application/json',
};

// Helper function for API requests
function apiRequest(method, url, body = null) {
    const params = { headers, tags: { name: url } };
    let response;
    
    if (method === 'GET') {
        response = http.get(url, params);
    } else if (method === 'POST') {
        response = http.post(url, body ? JSON.stringify(body) : null, params);
    } else if (method === 'PUT') {
        response = http.put(url, body ? JSON.stringify(body) : null, params);
    } else if (method === 'DELETE') {
        response = http.del(url, null, params);
    }
    
    return response;
}

// Setup - runs once before test
export function setup() {
    console.log('Starting load test for Restaurant Management System');
    console.log(`Menu URL: ${BASE_URLS.menu}`);
    console.log(`Orders URL: ${BASE_URLS.orders}`);
    console.log(`Inventory URL: ${BASE_URLS.inventory}`);
    console.log(`Billing URL: ${BASE_URLS.billing}`);
    
    // Verify services are up
    const services = ['menu', 'orders', 'inventory', 'billing'];
    const healthEndpoints = {
        menu: '/v1/menu/health',
        orders: '/v1/orders/health',
        inventory: '/v1/inventory/health',
        billing: '/v1/billing/health',
    };
    
    for (const service of services) {
        const res = http.get(`${BASE_URLS[service]}${healthEndpoints[service]}`);
        if (res.status !== 200) {
            console.error(`${service} service not healthy: ${res.status}`);
        }
    }
    
    // Get existing menu items for order tests
    const menuRes = http.get(`${BASE_URLS.menu}/v1/menu/items`);
    let menuItems = [];
    
    if (menuRes.status === 200) {
        try {
            const data = JSON.parse(menuRes.body);
            menuItems = data.items || [];
        } catch (e) {
            console.error('Failed to parse menu items');
        }
    }
    
    // Create a test menu item if none exist
    if (menuItems.length === 0) {
        const createRes = apiRequest('POST', `${BASE_URLS.menu}/v1/menu/items`, {
            name: 'Load Test Burger',
            description: 'A burger for load testing',
            price: '9.99',
            category: 'burgers',
            available: true,
            ingredients: ['beef_patty', 'bun'],
        });
        
        if (createRes.status === 201 || createRes.status === 200) {
            const item = JSON.parse(createRes.body);
            menuItems.push(item);
        }
    }
    
    return { menuItems };
}

// Main test function
export default function(data) {
    const menuItems = data.menuItems || [];
    
    group('Health Checks', function() {
        const menuHealth = apiRequest('GET', `${BASE_URLS.menu}/v1/menu/health`);
        check(menuHealth, {
            'menu health status 200': (r) => r.status === 200,
        }) || errorRate.add(1);
        
        const ordersHealth = apiRequest('GET', `${BASE_URLS.orders}/v1/orders/health`);
        check(ordersHealth, {
            'orders health status 200': (r) => r.status === 200,
        }) || errorRate.add(1);
        
        const inventoryHealth = apiRequest('GET', `${BASE_URLS.inventory}/v1/inventory/health`);
        check(inventoryHealth, {
            'inventory health status 200': (r) => r.status === 200,
        }) || errorRate.add(1);
        
        const billingHealth = apiRequest('GET', `${BASE_URLS.billing}/v1/billing/health`);
        check(billingHealth, {
            'billing health status 200': (r) => r.status === 200,
        }) || errorRate.add(1);
    });
    
    sleep(0.5);
    
    group('Menu Operations', function() {
        // List menu items
        const listRes = apiRequest('GET', `${BASE_URLS.menu}/v1/menu/items`);
        check(listRes, {
            'list menu status 200': (r) => r.status === 200,
            'list menu has items': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return body.items !== undefined;
                } catch (e) {
                    return false;
                }
            },
        }) || errorRate.add(1);
        
        // Get single item if available
        if (menuItems.length > 0) {
            const item = menuItems[randomIntBetween(0, menuItems.length - 1)];
            if (item && item.id) {
                const getRes = apiRequest('GET', `${BASE_URLS.menu}/v1/menu/items/${item.id}`);
                check(getRes, {
                    'get item status 200': (r) => r.status === 200,
                }) || errorRate.add(1);
            }
        }
        
        // Validate menu items
        if (menuItems.length > 0) {
            const item = menuItems[0];
            const validationRes = apiRequest('POST', `${BASE_URLS.menu}/v1/menu/validation`, {
                items: [{ itemId: item.id, qty: 1, unitPrice: parseFloat(item.price) || 9.99 }],
                idempotencyKey: `validation_${randomString(8)}`,
            });
            check(validationRes, {
                'validation status 200': (r) => r.status === 200,
            }) || errorRate.add(1);
        }
    });
    
    sleep(0.5);
    
    group('Inventory Operations', function() {
        // List stock
        const stockRes = apiRequest('GET', `${BASE_URLS.inventory}/v1/inventory/stock`);
        check(stockRes, {
            'list stock status 200': (r) => r.status === 200,
            'list stock has items': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return body.items !== undefined;
                } catch (e) {
                    return false;
                }
            },
        }) || errorRate.add(1);
    });
    
    sleep(0.5);
    
    group('Full Order Flow', function() {
        if (menuItems.length === 0) {
            console.log('No menu items available, skipping order flow');
            return;
        }
        
        const startTime = Date.now();
        const item = menuItems[0];
        const idempotencyKey = `order_${__VU}_${__ITER}_${randomString(8)}`;
        const customerId = `customer_${__VU}`;
        
        // Create order
        const orderRes = apiRequest('POST', `${BASE_URLS.orders}/v1/orders`, {
            customer: { id: customerId, name: `Test User ${__VU}` },
            items: [{ itemId: item.id, qty: randomIntBetween(1, 3), unitPrice: parseFloat(item.price) || 9.99 }],
            idempotencyKey: idempotencyKey,
        });
        
        const orderCreatedOk = check(orderRes, {
            'order created status 2xx': (r) => r.status >= 200 && r.status < 300,
            'order has orderId': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return body.orderId !== undefined;
                } catch (e) {
                    return false;
                }
            },
        });
        
        if (!orderCreatedOk) {
            errorRate.add(1);
            console.log(`Order creation failed: ${orderRes.status} - ${orderRes.body}`);
            return;
        }
        
        orderCreated.add(1);
        
        let orderData;
        try {
            orderData = JSON.parse(orderRes.body);
        } catch (e) {
            errorRate.add(1);
            return;
        }
        
        const orderId = orderData.orderId;
        const paymentIntentId = orderData.paymentIntentId;
        
        // Verify order status
        sleep(0.2);
        const orderStatusRes = apiRequest('GET', `${BASE_URLS.orders}/v1/orders/${orderId}`);
        check(orderStatusRes, {
            'order status retrieved': (r) => r.status === 200,
            'order in valid state': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return ['validating', 'reserved', 'paid'].includes(body.status);
                } catch (e) {
                    return false;
                }
            },
        }) || errorRate.add(1);
        
        // Capture payment if available
        if (paymentIntentId && paymentIntentId !== 'null') {
            sleep(0.2);
            const captureRes = apiRequest('POST', `${BASE_URLS.billing}/v1/billing/payments/${paymentIntentId}/capture`);
            
            const captureOk = check(captureRes, {
                'payment captured status 2xx': (r) => r.status >= 200 && r.status < 300,
            });
            
            if (captureOk) {
                paymentCaptured.add(1);
            } else {
                errorRate.add(1);
            }
            
            // Verify final order status
            sleep(0.5);
            const finalStatusRes = apiRequest('GET', `${BASE_URLS.orders}/v1/orders/${orderId}`);
            check(finalStatusRes, {
                'final order status retrieved': (r) => r.status === 200,
            }) || errorRate.add(1);
        }
        
        const duration = Date.now() - startTime;
        orderDuration.add(duration);
    });
    
    sleep(randomIntBetween(1, 3));
}

// Teardown - runs once after test
export function teardown(data) {
    console.log('Load test completed');
    console.log(`Total menu items used: ${data.menuItems ? data.menuItems.length : 0}`);
}
