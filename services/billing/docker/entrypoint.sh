#!/bin/sh
set -e

# Wait for database to be ready (if external)
# sleep 2

# Run database migrations on first start
if [ ! -f /app/var/.initialized ]; then
    echo "Initializing database..."
    php bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || php bin/console doctrine:schema:create --no-interaction
    touch /app/var/.initialized
    echo "Database initialized."
fi

# Clear and warm up cache for production
if [ "$APP_ENV" = "prod" ]; then
    php bin/console cache:clear --no-warmup --env=prod
    php bin/console cache:warmup --env=prod
fi

exec "$@"
