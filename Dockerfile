# syntax=docker/dockerfile:1.7

# Override these with digest-pinned references in release builds.
ARG PHP_IMAGE=php:8.4-fpm-bookworm
ARG NODE_IMAGE=node:22-bookworm-slim
ARG COMPOSER_IMAGE=composer:2.8
ARG NGINX_IMAGE=nginx:1.27-alpine

FROM ${COMPOSER_IMAGE} AS composer-bin

FROM ${PHP_IMAGE} AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
    && rm -rf /tmp/pear /var/lib/apt/lists/*

COPY docker/php/conf.d/production.ini /usr/local/etc/php/conf.d/zz-production.ini

FROM php-base AS vendor

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    && php artisan filament:assets \
    && rm -rf /root/.composer/cache

FROM ${NODE_IMAGE} AS assets

WORKDIR /var/www
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./vite.config.js
RUN npm run build

FROM php-base AS app-base

WORKDIR /var/www
COPY --from=vendor --chown=www-data:www-data /var/www /var/www
COPY --from=assets --chown=www-data:www-data /var/www/public/build /var/www/public/build
COPY --chmod=755 entrypoint.sh /usr/local/bin/entrypoint

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["php-fpm", "-F"]

FROM ${NGINX_IMAGE} AS nginx

COPY docker/nginx/templates/default.conf.template /etc/nginx/templates/default.conf.template
COPY --from=app-base /var/www/public /var/www/public
ENV NGINX_ENVSUBST_FILTER=^NGINX_
EXPOSE 80

# Keep app as the default build target; Compose explicitly selects nginx.
FROM app-base AS app
