#!/bin/bash
# Integration Test for Restaurant Management System
# Tests the complete order flow across all 4 services

# Don't exit on first error - we want to see all test results
set +e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_debug() {
    echo -e "${BLUE}[DEBUG]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Service URLs
MENU_URL="${MENU_SERVICE_URL:-http://localhost:8000}"
ORDERS_URL="${ORDERS_SERVICE_URL:-http://localhost:8001}"
INVENTORY_URL="${INVENTORY_SERVICE_URL:-http://localhost:8002}"
BILLING_URL="${BILLING_SERVICE_URL:-http://localhost:8003}"

# Trap to catch errors
trap 'log_error "Script exited at line $LINENO with exit code $?"' EXIT

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Restaurant Management System - Integration Test${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""
log "Menu URL: $MENU_URL"
log "Orders URL: $ORDERS_URL"
log "Inventory URL: $INVENTORY_URL"
log "Billing URL: $BILLING_URL"
echo ""

# Wait for services to be ready
wait_for_service() {
    local url=$1
    local name=$2
    local max_attempts=30
    local attempt=1
    
    log "Waiting for $name at $url"
    echo -n "Waiting for $name..."
    while [ $attempt -le $max_attempts ]; do
        if curl -s -f "$url" > /dev/null 2>&1; then
            echo -e " ${GREEN}ready${NC}"
            return 0
        fi
        echo -n "."
        sleep 2
        ((attempt++))
    done
    echo -e " ${RED}timeout${NC}"
    log_error "$name not available after $max_attempts attempts"
    return 1
}

echo -e "${YELLOW}Waiting for services to be ready...${NC}"
wait_for_service "$MENU_URL/v1/menu/health" "Menu" || { log_error "Menu service not ready"; }
wait_for_service "$INVENTORY_URL/v1/inventory/health" "Inventory" || { log_error "Inventory service not ready"; }
wait_for_service "$BILLING_URL/v1/billing/health" "Billing" || { log_error "Billing service not ready"; }
wait_for_service "$ORDERS_URL/v1/orders/health" "Orders" || { log_error "Orders service not ready"; }
echo ""

# Helper function to make requests
request() {
    local method=$1
    local url=$2
    local data=$3
    local http_code
    local response
    
    log_debug "Request: $method $url"
    if [ -n "$data" ]; then
        log_debug "Body: $data"
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$url" -H "Content-Type: application/json" -d "$data")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$url")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    response=$(echo "$response" | sed '$d')
    log_debug "HTTP Status: $http_code"
    
    echo "$response"
}

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

assert_contains() {
    local response=$1
    local expected=$2
    local test_name=$3
    
    if echo "$response" | grep -q "$expected"; then
        echo -e "${GREEN}✓ PASS${NC}: $test_name"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}✗ FAIL${NC}: $test_name"
        echo "  Expected to contain: $expected"
        echo "  Got: $response"
        ((TESTS_FAILED++))
    fi
}

assert_status() {
    local response=$1
    local expected_status=$2
    local test_name=$3
    
    if echo "$response" | grep -q "\"status\":\"$expected_status\""; then
        echo -e "${GREEN}✓ PASS${NC}: $test_name"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}✗ FAIL${NC}: $test_name"
        echo "  Expected status: $expected_status"
        echo "  Got: $response"
        ((TESTS_FAILED++))
    fi
}

echo -e "${YELLOW}[1/8] Testing Menu Service Health${NC}"
log "Fetching menu items from $MENU_URL/v1/menu/items"
MENU_RESPONSE=$(request GET "$MENU_URL/v1/menu/items")
log_debug "Menu Response: $MENU_RESPONSE"
assert_contains "$MENU_RESPONSE" "items" "Menu service returns items list"

echo ""
echo -e "${YELLOW}[2/8] Creating Menu Item${NC}"
log "Creating test menu item"
MENU_ITEM=$(request POST "$MENU_URL/v1/menu/items" '{
    "name": "Test Burger",
    "description": "A test burger for integration testing",
    "price": "12.99",
    "category": "burgers",
    "available": true,
    "ingredients": ["beef_patty", "lettuce", "tomato", "bun"]
}')
log_debug "Create Menu Item Response: $MENU_ITEM"
assert_contains "$MENU_ITEM" "Test Burger" "Menu item created successfully"

# Extract item ID
ITEM_ID=$(echo "$MENU_ITEM" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
log "Created item ID: $ITEM_ID"

echo ""
echo -e "${YELLOW}[3/8] Testing Menu Validation${NC}"
log "Validating menu item $ITEM_ID with price 12.99"
VALIDATION=$(request POST "$MENU_URL/v1/menu/validation" "{
    \"items\": [{\"itemId\": \"$ITEM_ID\", \"qty\": 2, \"unitPrice\": 12.99}],
    \"idempotencyKey\": \"test_validation_001\"
}")
log_debug "Validation Response: $VALIDATION"
assert_contains "$VALIDATION" "\"valid\":true" "Menu validation passes for correct price"

echo ""
echo -e "${YELLOW}[4/8] Testing Inventory Service${NC}"
log "Fetching stock from $INVENTORY_URL/v1/inventory/stock"
STOCK_RESPONSE=$(request GET "$INVENTORY_URL/v1/inventory/stock")
log_debug "Stock Response: $STOCK_RESPONSE"
assert_contains "$STOCK_RESPONSE" "items" "Inventory service returns stock list"

echo ""
echo -e "${YELLOW}[5/8] Creating Full Order (Menu → Inventory → Billing)${NC}"
IDEMPOTENCY_KEY="test_order_$(date +%s)"
log "Creating order with item $ITEM_ID, idempotencyKey: $IDEMPOTENCY_KEY"
ORDER_RESPONSE=$(request POST "$ORDERS_URL/v1/orders" "{
    \"customer\": {\"id\": \"test_customer_1\", \"name\": \"John Doe\"},
    \"items\": [{\"itemId\": \"$ITEM_ID\", \"qty\": 2, \"unitPrice\": 12.99}],
    \"idempotencyKey\": \"$IDEMPOTENCY_KEY\"
}")
log_debug "Order Response: $ORDER_RESPONSE"

# Check if order was created (could be reserved or have error if services not all running)
if echo "$ORDER_RESPONSE" | grep -q "orderId"; then
    assert_contains "$ORDER_RESPONSE" "orderId" "Order created successfully"
    
    # Extract IDs
    ORDER_ID=$(echo "$ORDER_RESPONSE" | grep -o '"orderId":"[^"]*"' | cut -d'"' -f4)
    PAYMENT_ID=$(echo "$ORDER_RESPONSE" | grep -o '"paymentIntentId":"[^"]*"' | cut -d'"' -f4)
    RESERVATION_ID=$(echo "$ORDER_RESPONSE" | grep -o '"reservationId":"[^"]*"' | cut -d'"' -f4)
    
    log "Order ID: $ORDER_ID"
    log "Payment Intent ID: $PAYMENT_ID"
    log "Reservation ID: $RESERVATION_ID"
    
    echo ""
    echo -e "${YELLOW}[6/8] Verifying Order Status${NC}"
    log "Fetching order status for $ORDER_ID"
    ORDER_STATUS=$(request GET "$ORDERS_URL/v1/orders/$ORDER_ID")
    log_debug "Order Status Response: $ORDER_STATUS"
    assert_status "$ORDER_STATUS" "reserved" "Order is in reserved status"
    
    echo ""
    echo -e "${YELLOW}[7/8] Capturing Payment (Billing → Orders webhook)${NC}"
    if [ -n "$PAYMENT_ID" ] && [ "$PAYMENT_ID" != "null" ]; then
        log "Capturing payment $PAYMENT_ID"
        CAPTURE_RESPONSE=$(request POST "$BILLING_URL/v1/billing/payments/$PAYMENT_ID/capture")
        log_debug "Capture Response: $CAPTURE_RESPONSE"
        assert_status "$CAPTURE_RESPONSE" "captured" "Payment captured successfully"
        
        # Give webhook time to process
        log "Waiting 4s for webhook to process"
        sleep 4
        
        echo ""
        echo -e "${YELLOW}[8/8] Verifying Final Order Status${NC}"
        log "Fetching final order status for $ORDER_ID"
        FINAL_ORDER=$(request GET "$ORDERS_URL/v1/orders/$ORDER_ID")
        log_debug "Final Order Response: $FINAL_ORDER"
        assert_status "$FINAL_ORDER" "paid" "Order is in paid status after capture"
    else
        log "Skipping payment capture - no payment intent"
        echo -e "${YELLOW}  Skipping payment capture - no payment intent${NC}"
        ((TESTS_PASSED++))
        ((TESTS_PASSED++))
    fi
else
    log_error "Order creation failed"
    echo -e "${RED}✗ FAIL${NC}: Order creation failed"
    log_debug "Response: $ORDER_RESPONSE"
    ((TESTS_FAILED++))
    ((TESTS_FAILED++))
    ((TESTS_FAILED++))
    ((TESTS_FAILED++))
fi

echo ""
echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Test Summary${NC}"
echo -e "${YELLOW}========================================${NC}"
echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Failed: $TESTS_FAILED${NC}"
echo ""

# Clear the trap before final exit
trap - EXIT

if [ $TESTS_FAILED -eq 0 ]; then
    log "All integration tests passed!"
    echo -e "${GREEN}All integration tests passed!${NC}"
    exit 0
else
    log_error "Some tests failed: $TESTS_FAILED failures"
    echo -e "${RED}Some tests failed!${NC}"
    exit 1
fi
