#!/bin/bash
# Seed all services with default data

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "Seeding Menu Service..."
cd "$PROJECT_ROOT/services/menu"
php bin/console app:seed

echo ""
echo "Seeding Inventory Service..."
cd "$PROJECT_ROOT/services/inventory"
php bin/console app:seed

echo ""
echo "All services seeded successfully!"
