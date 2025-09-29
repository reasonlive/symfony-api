#!/bin/bash
set -e

echo "Waiting for database to be ready..."

# Wait for database
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
    echo "Database is unavailable - sleeping"
    sleep 5
done

echo "Database is ready!"

# Run migrations
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

# Load fixtures (only in dev environment)
if [ "$APP_ENV" = "dev" ]; then
    echo "Loading fixtures..."
    php bin/console doctrine:fixtures:load --no-interaction
fi

echo "Starting application..."
exec "$@"