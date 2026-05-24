#!/bin/bash

# Auto-create .env fallback if missing on VPS
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Ensure composer dependencies are installed (live directory fix)
if [ ! -f vendor/autoload.php ]; then
    echo "Vendor directory not found. Installing composer dependencies..."
    composer install --no-interaction --optimize-autoloader --no-dev
fi

# Ensure SQLite database file exists
if [ ! -f database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch database/database.sqlite
fi

# Set permissions
chmod -R 777 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache database

# Generate application key if not set
php artisan key:generate --force 2>/dev/null || true

# Link storage
php artisan storage:link 2>/dev/null || true

# Run migrations
php artisan migrate --force 2>/dev/null || true

# Clear existing cache
php artisan optimize:clear

# Optimize configurations for production
php artisan optimize

# Install octane with swoole (non-interactive)
php artisan octane:install --server=swoole --no-interaction 2>/dev/null || true

# Start Laravel Octane (Swoole) in the foreground
echo "Starting Laravel Octane..."
exec php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=auto
