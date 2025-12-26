#!/bin/bash

# Script to start all microservices locally for debugging with Xdebug
# Each service runs on its designated port with Xdebug enabled

set -e

# Color output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=====================================${NC}"
echo -e "${BLUE}Starting Local Microservices Setup${NC}"
echo -e "${BLUE}=====================================${NC}"
echo ""

# Get the script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Function to setup a service
setup_service() {
    local service_name=$1
    local service_dir="$SCRIPT_DIR/services/$service_name"
    
    echo -e "${GREEN}Setting up $service_name service...${NC}"
    
    cd "$service_dir"
    
    # Install dependencies if vendor doesn't exist
    if [ ! -d "vendor" ]; then
        echo "Installing composer dependencies..."
        composer install --no-interaction
    fi
    
    # Run migrations to create database
    echo "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction || true
    
    echo -e "${GREEN}✓ $service_name setup complete${NC}"
    echo ""
}

# Setup all services
setup_service "menu"
setup_service "inventory"
setup_service "billing"
setup_service "orders"

echo -e "${BLUE}=====================================${NC}"
echo -e "${GREEN}All services are ready!${NC}"
echo -e "${BLUE}=====================================${NC}"
echo ""
echo "To debug with VSCode:"
echo "1. Open the Debug panel (Ctrl+Shift+D or Cmd+Shift+D)"
echo "2. Select 'Listen for Xdebug' from the dropdown"
echo "3. Click the green play button to start listening"
echo "4. Set breakpoints in your code"
echo "5. In a separate terminal, run one of the services:"
echo ""
echo -e "${BLUE}   cd services/menu && php -S localhost:8000 -t public${NC}"
echo -e "${BLUE}   cd services/inventory && php -S localhost:8002 -t public${NC}"
echo -e "${BLUE}   cd services/billing && php -S localhost:8003 -t public${NC}"
echo -e "${BLUE}   cd services/orders && php -S localhost:8001 -t public${NC}"
echo ""
echo "Or use VSCode launch configurations to start individual services."
echo ""
echo "Services will be available at:"
echo "  - Menu:      http://localhost:8000"
echo "  - Orders:    http://localhost:8001"
echo "  - Inventory: http://localhost:8002"
echo "  - Billing:   http://localhost:8003"
echo ""
echo "Xdebug log: /tmp/xdebug.log"
echo ""
