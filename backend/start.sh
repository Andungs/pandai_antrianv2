#!/bin/bash

# Auto-create .env fallback if missing on VPS
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Ensure composer dependencies are installed (live directory fix)
if [ ! -f vendor/autoload.php ]; then
    echo "Vendor directory not found. Installing composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Generate application key if not set
php artisan key:generate --force

# Clear existing cache
php artisan optimize:clear

# Optimize configurations for production
php artisan optimize

# Install octane swoole if not present (ignore error if already installed)
php artisan octane:install --server=swoole || true

# Start Laravel Octane (Swoole) in the foreground
echo "Starting Laravel Octane..."
exec php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=auto
