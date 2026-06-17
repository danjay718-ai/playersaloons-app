FROM php:8.3-fpm-alpine

# Install system dependencies + nginx
RUN apk add --no-cache \
    bash \
    curl \
    nginx \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    icu-dev \
    libzip-dev \
    nodejs \
    npm \
    autoconf \
    g++ \
    make

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql bcmath gd intl zip pcntl opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy app
COPY . /var/www

# Copy nginx config
COPY docker/nginx/render.conf /etc/nginx/http.d/default.conf

# Copy startup script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# Permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 10000

CMD ["/start.sh"]
