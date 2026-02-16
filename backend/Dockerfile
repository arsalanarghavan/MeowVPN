FROM php:8.2-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    oniguruma-dev \
    libxml2-dev \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache \
    sockets \
    xml

# Install Redis extension
RUN apk add --no-cache pcre-dev $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del pcre-dev $PHPIZE_DEPS

# Configure opcache for production
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.enable=1'; \
    echo 'opcache.jit_buffer_size=100M'; \
    echo 'opcache.jit=1255'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP for production
RUN { \
    echo 'memory_limit=256M'; \
    echo 'upload_max_filesize=50M'; \
    echo 'post_max_size=50M'; \
    echo 'max_execution_time=60'; \
    echo 'expose_php=Off'; \
} > /usr/local/etc/php/conf.d/production.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for caching
COPY composer.json ./
COPY composer.lock* ./

# Install dependencies (no dev)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application
COPY . .

# Ensure storage and cache directories exist
RUN mkdir -p /var/www/html/storage/app/public \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/bootstrap/cache

# Generate optimized autoloader (skip scripts to avoid needing APP_KEY during build)
RUN composer dump-autoload --optimize --no-scripts

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Change current user to www-data
USER www-data

# Expose port 8000
EXPOSE 8000

# Entrypoint: run migrations then start server
CMD ["sh", "-c", "php artisan package:discover --ansi && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
