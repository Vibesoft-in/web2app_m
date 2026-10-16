FROM php:8.3-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

# PHP extensions (pcntl required for Horizon)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl

# Redis extension (via PECL)
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Non-root user (matches typical www-data uid 82 on alpine)
RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/bash -D app
USER app

WORKDIR /var/www

CMD ["php-fpm"]
