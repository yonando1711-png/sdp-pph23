#!/bin/bash
set -e

# Ensure the database file exists if we are using sqlite and it's not present in the volume yet
if [ "$DB_CONNECTION" = "sqlite" ] || grep -q "DB_CONNECTION=sqlite" .env 2>/dev/null; then
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        echo "Creating SQLite database file..."
        touch /var/www/html/database/database.sqlite
        chown www-data:www-data /var/www/html/database/database.sqlite
    fi
fi

# Clear caches and optimize
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations (force is needed in production environments)
echo "Running migrations..."
php artisan migrate --force

# Start Apache in the foreground
echo "Starting Apache..."
exec apache2-foreground
