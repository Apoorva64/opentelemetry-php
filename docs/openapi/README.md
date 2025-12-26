# OpenAPI Specifications

This folder contains the OpenAPI 3.0 specifications for all restaurant management services.

## Services

| Service | Spec File | Port |
|---------|-----------|------|
| Menu | [menu-openapi.json](menu-openapi.json) | 8000 |
| Orders | [orders-openapi.json](orders-openapi.json) | 8001 |
| Inventory | [inventory-openapi.json](inventory-openapi.json) | 8002 |
| Billing | [billing-openapi.json](billing-openapi.json) | 8003 |

## Generating Specs

The OpenAPI specifications are auto-generated from PHP 8 attributes using [zircote/swagger-php](https://github.com/zircote/swagger-php).

### From Running Containers

```bash
# Export all specs
./scripts/export-openapi.sh

# Or export individually
docker compose exec menu php bin/console app:openapi:export -o openapi.json
docker compose exec orders php bin/console app:openapi:export -o openapi.json
docker compose exec inventory php bin/console app:openapi:export -o openapi.json
docker compose exec billing php bin/console app:openapi:export -o openapi.json

# YAML format
docker compose exec menu php bin/console app:openapi:export -o openapi.yaml -f yaml
```

## Using the Specs

### Import into Postman/Insomnia
1. Download the JSON spec files
2. In Postman: Import > Raw Text > Paste JSON
3. In Insomnia: Import > From File

### Generate Client SDKs
Use [OpenAPI Generator](https://openapi-generator.tech/) to generate client libraries:

```bash
# TypeScript client
npx @openapitools/openapi-generator-cli generate \
  -i menu-openapi.json \
  -g typescript-fetch \
  -o ./clients/menu

# Python client
npx @openapitools/openapi-generator-cli generate \
  -i menu-openapi.json \
  -g python \
  -o ./clients/menu-python
```

### Validate Specs
```bash
npx swagger-cli validate menu-openapi.json
```
