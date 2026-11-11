# Use official PHP 8.2 CLI image (composer.json pins platform.php to 8.2 to match)
FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring bcmath gd

# Set working directory
WORKDIR /var/www/html

# Copy project files (see .dockerignore)
COPY . .

# Install Composer and production dependencies only
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Create required Laravel directories
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Expose Render port
EXPOSE 8080

# Run migrations, start the scheduler (stale-chat cleanup) in the background,
# then serve. NOTE: `php -S` is single-threaded; see README "Production server".
CMD sh -c "php artisan migrate --force; php artisan schedule:work > /dev/null 2>&1 & php -S 0.0.0.0:${PORT:-8080} -t public"
