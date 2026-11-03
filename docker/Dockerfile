# syntax=docker/dockerfile:1.7
#
# Imagen de producción de Rifax.
#
#   assets  → compila CSS/JS con Vite e instala Puppeteer (sin descargar Chrome)
#   app     → PHP-FPM 8.5 + Chromium del sistema + Node (para boletos con Browsershot)
#   web     → Caddy con HTTPS automático, sirve /public y pasa PHP a "app"
#
# Construir:  docker compose -f compose.prod.yaml build

ARG PHP_VERSION=8.5
ARG NODE_VERSION=26

########################################################################
FROM node:${NODE_VERSION}-trixie-slim AS assets
WORKDIR /app
ENV PUPPETEER_SKIP_DOWNLOAD=true \
    CI=true
# Node 25+ ya no incluye corepack: pnpm se instala con npm.
RUN npm install -g pnpm@12.16.1
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
# Tailwind escanea vistas y código PHP para generar solo las clases usadas.
COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
COPY public ./public
RUN pnpm build

########################################################################
FROM node:${NODE_VERSION}-trixie-slim AS node

########################################################################
FROM php:${PHP_VERSION}-fpm-trixie AS app

ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
        chromium fonts-liberation fonts-noto-color-emoji fonts-noto-core \
        libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        unzip git ca-certificates postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip gd bcmath exif pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && apt-get install -y --no-install-recommends libpq5 libicu76 libzip5 libpng16-16t64 libjpeg62-turbo libfreetype6 \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Node para que Browsershot ejecute Puppeteer (usa el Chromium del sistema).
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-rifax.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-rifax.conf

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=assets /app/node_modules ./node_modules

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && rm -f public/storage && ln -s ../storage/app/public public/storage \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
                storage/framework/views storage/logs bootstrap/cache /var/www/.cache /var/www/.config \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/.cache /var/www/.config

COPY docker/entrypoint.sh /usr/local/bin/rifax-entrypoint
RUN chmod +x /usr/local/bin/rifax-entrypoint

# Chromium y Puppeteer escriben su caché en $HOME.
ENV HOME=/var/www \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

USER www-data
ENTRYPOINT ["rifax-entrypoint"]
CMD ["php-fpm"]

########################################################################
FROM caddy:2.11-alpine AS web
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public
