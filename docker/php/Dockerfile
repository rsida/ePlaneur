# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5
ARG NGINX_VERSION=1.28

# ---------------------------------------------------------------------------
# Base PHP-FPM image shared by every environment
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm AS php_base

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions apcu gd intl opcache pdo_mysql zip

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/zz-app.ini

# ---------------------------------------------------------------------------
# Development image: sources are bind-mounted, Xdebug available
# ---------------------------------------------------------------------------
FROM php_base AS php_dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions xdebug \
    && git config --system --add safe.directory /app

COPY docker/php/conf.d/xdebug.ini $PHP_INI_DIR/conf.d/zz-xdebug.ini

# ---------------------------------------------------------------------------
# Production image: sources, vendors and compiled assets baked in
# ---------------------------------------------------------------------------
FROM php_base AS php_prod

ENV APP_ENV=prod \
    APP_DEBUG=0

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/conf.d/prod.ini $PHP_INI_DIR/conf.d/zz-prod.ini

# Install vendors first to benefit from the layer cache
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY . .

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd \
    && php bin/console asset-map:compile \
    && mkdir -p var/uploads \
    && chown -R www-data:www-data var

# ---------------------------------------------------------------------------
# Production nginx image: serves the compiled public/ directory
# ---------------------------------------------------------------------------
FROM nginx:${NGINX_VERSION}-alpine AS nginx_prod

COPY docker/nginx/symfony.conf /etc/nginx/symfony.conf
COPY docker/nginx/prod.conf.template /etc/nginx/templates/default.conf.template
COPY --from=php_prod /app/public /app/public
