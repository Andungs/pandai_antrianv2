FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy existing application directory contents
COPY . .

# Install PHP dependencies (including Octane if not exists)
RUN rm -rf vendor && rm -f bootstrap/cache/*.php && \
    composer require laravel/octane swoole/ide-helper --no-interaction --ignore-platform-reqs && \
    composer install --no-interaction --optimize-autoloader --ignore-platform-reqs --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www


# Expose ports for Laravel Artisan 
EXPOSE 8000
EXPOSE 8080

# Start command
CMD bash -c "\
    composer install || composer update; \
    npm install; \
    php artisan key:generate 2>/dev/null || true; \
    php artisan migrate --force 2>/dev/null || true; \
    php artisan reverb:start --host=0.0.0.0 --port=8080 & \
    php artisan serve --host=0.0.0.0 --port=8000 & \
    npm run dev -- --host 0.0.0.0 \
"
