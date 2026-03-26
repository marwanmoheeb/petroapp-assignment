FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize


FROM php:8.2-apache

RUN a2enmod rewrite headers && \
    apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        zip \
        intl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p storage bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=local \
    APP_DEBUG=true \
    LOG_CHANNEL=stderr

EXPOSE 80
