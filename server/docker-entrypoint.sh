#!/bin/sh
set -e

# Run optimizations if in production
if [ "$APP_ENV" = "production" ]; then
    echo "Running production optimizations..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "Running in development mode..."
    php artisan optimize:clear
fi

# Execute the main command (php artisan serve or whatever is in CMD)
exec "$@"
