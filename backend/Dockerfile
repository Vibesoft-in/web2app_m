# ==============================================================================
# Production Multi-Stage Dockerfile for Appify Backend (Laravel 11)
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Build Dependencies via Composer
# ------------------------------------------------------------------------------
FROM composer:2.7 AS composer-builder

WORKDIR /app

# Copy dependency manifests first for build layer caching
COPY composer.json composer.lock ./

# Install production dependencies only (no dev packages, optimized autoloader)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# Copy application source code
COPY . .

# Dump final autoloader with full classmap
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ------------------------------------------------------------------------------
# Stage 2: Production PHP-FPM Runtime
# ------------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS production

LABEL maintainer="Appify Team <ops@appify.dev>"
LABEL description="Production PHP 8.3 FPM image for Appify White-Label API"

# Install production system libraries
RUN apk add --no-cache \
    bash \
    curl \
    fcgi \
    icu-data-full \
    icu-libs \
    libjpeg-turbo \
    libpng \
    libwebp \
    libzip \
    supervisor \
    logrotate \
    tini \
    $PHPIZE_DEPS \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    icu-dev

# Configure and compile PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        zip \
        exif

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --purge $PHPIZE_DEPS freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev libzip-dev icu-dev

# Configure production PHP and Opcache
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Apply hardened PHP production settings
RUN echo "expose_php = Off" >> "$PHP_INI_DIR/php.ini" \
    && echo "memory_limit = 256M" >> "$PHP_INI_DIR/php.ini" \
    && echo "upload_max_filesize = 10M" >> "$PHP_INI_DIR/php.ini" \
    && echo "post_max_size = 12M" >> "$PHP_INI_DIR/php.ini" \
    && echo "max_execution_time = 30" >> "$PHP_INI_DIR/php.ini"

# Apply Opcache performance tuning
RUN echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" \
    && echo "opcache.enable_cli=0" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" \
    && echo "opcache.memory_consumption=256" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" \
    && echo "opcache.interned_strings_buffer=16" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" \
    && echo "opcache.max_accelerated_files=20000" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" \
    && echo "opcache.validate_timestamps=0" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" \
    && echo "opcache.save_comments=1" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" \
    && echo "opcache.fast_shutdown=1" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini"

# Create non-root system user and group
RUN addgroup -g 1000 appify && adduser -u 1000 -G appify -s /bin/bash -D appify

WORKDIR /var/www

# Copy vendor from builder stage
COPY --from=composer-builder --chown=appify:appify /app/vendor ./vendor

# Copy application files
COPY --chown=appify:appify . .

# Ensure storage and cache directories exist with strict permissions
RUN mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R appify:appify storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Healthcheck script using php-fpm status or ping
COPY --chown=appify:appify infra/docker/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh || true

USER appify

EXPOSE 9000

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php-fpm"]
