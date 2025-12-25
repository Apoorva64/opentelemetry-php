#!/bin/bash
# Export OpenAPI specifications from all services

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
OUTPUT_DIR="${ROOT_DIR}/docs/openapi"

mkdir -p "$OUTPUT_DIR"

echo "Exporting OpenAPI specifications..."

# Export from each service
for service in menu orders inventory billing; do
    echo "Exporting $service service OpenAPI spec..."
    docker compose exec "$service" php bin/console app:openapi:export -o /tmp/openapi.json 2>/dev/null || {
        echo "  Note: Run 'docker compose up -d' and wait for services to start first"
        exit 1
    }
    docker compose cp "$service":/tmp/openapi.json "$OUTPUT_DIR/${service}-openapi.json"
    echo "  -> $OUTPUT_DIR/${service}-openapi.json"
done

echo ""
echo "All OpenAPI specifications exported to $OUTPUT_DIR"
echo ""
echo "To view the specs, you can use:"
echo "  - Swagger Editor: https://editor.swagger.io/"
echo "  - Import into Postman or Insomnia"
