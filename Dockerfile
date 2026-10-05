# ============================================
# Stage 1: Install PHP dependencies
# ============================================
FROM php:8.5-fpm-alpine AS composer

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ============================================
# Stage 2: Build frontend assets
# ============================================
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ============================================
# Stage 3: Production PHP-FPM
# ============================================
FROM php:8.5-fpm-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        bcmath \
        mbstring \
        intl \
        opcache \
        pcntl

# Install Redis extension
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

# Clear cache
RUN rm -rf /tmp/* /var/cache/apk/*

WORKDIR /app

# Copy composer dependencies from stage 1
COPY --from=composer /app/vendor /app/vendor
COPY --from=composer /app/composer.json /app/composer.json

# Copy frontend assets from stage 2
COPY --from=frontend /app/public/build /app/public/build

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# Copy PHP-FPM config
COPY docker/php-fpm/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 9000

CMD ["php-fpm"]
