#!/bin/bash

# Test Error Endpoints Script
# Calls various error endpoints in the menu service to test observability

MENU_URL="${MENU_URL:-http://localhost:8000}"

echo "=============================================="
echo "Testing Error Endpoints on Menu Service"
echo "Base URL: $MENU_URL"
echo "=============================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

call_endpoint() {
    local name="$1"
    local endpoint="$2"
    local expected_error="${3:-true}"
    
    echo -e "${YELLOW}Testing: $name${NC}"
    echo "  Endpoint: $endpoint"
    
    response=$(curl -s -w "\n%{http_code}" "$MENU_URL$endpoint" 2>&1)
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$expected_error" = "true" ]; then
        if [ "$http_code" -ge 400 ]; then
            echo -e "  Status: ${GREEN}$http_code (error as expected)${NC}"
        else
            echo -e "  Status: ${RED}$http_code (expected error)${NC}"
        fi
    else
        if [ "$http_code" -lt 400 ]; then
            echo -e "  Status: ${GREEN}$http_code${NC}"
        else
            echo -e "  Status: ${RED}$http_code${NC}"
        fi
    fi
    echo "  Response: $(echo "$body" | head -c 200)"
    echo ""
}

# Health check first
echo "--- Health Check ---"
call_endpoint "Health Check" "/v1/menu/health" false
sleep 0.5

echo "--- Log Levels Test ---"
call_endpoint "All Log Levels" "/v1/menu/test/log-levels" false
sleep 0.5

echo "--- HTTP Error Codes ---"
call_endpoint "HTTP 400 Bad Request" "/v1/menu/test/error/http/400"
sleep 0.3
call_endpoint "HTTP 404 Not Found" "/v1/menu/test/error/http/404"
sleep 0.3
call_endpoint "HTTP 500 Internal Server Error" "/v1/menu/test/error/http/500"
sleep 0.3
call_endpoint "HTTP 503 Service Unavailable" "/v1/menu/test/error/http/503"
sleep 0.5

echo "--- PHP Errors ---"
call_endpoint "Division by Zero" "/v1/menu/test/error/division"
sleep 0.3
call_endpoint "Type Error" "/v1/menu/test/error/type"
sleep 0.3
call_endpoint "Undefined Index" "/v1/menu/test/error/undefined"
sleep 0.5

echo "--- Exceptions ---"
call_endpoint "Runtime Exception" "/v1/menu/test/error/exception"
sleep 0.3
call_endpoint "Nested Exception Chain" "/v1/menu/test/error/nested-exception"
sleep 0.5

echo "--- Infrastructure Errors ---"
call_endpoint "Database Error" "/v1/menu/test/error/database"
sleep 0.3
call_endpoint "Downstream Service Error" "/v1/menu/test/error/downstream"
sleep 0.5

echo "--- Slow Endpoint (2 seconds) ---"
call_endpoint "Timeout Simulation (2s)" "/v1/menu/test/error/timeout?seconds=2" false
sleep 0.5

echo "=============================================="
echo "Test Complete!"
echo ""
echo "Check observability tools:"
echo "  - Jaeger UI: http://localhost:16686"
echo "  - Grafana:   http://localhost:9030"
echo "  - Loki:      http://localhost:3100"
echo "=============================================="
