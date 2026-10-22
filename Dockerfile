# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Etapa 1: compilar assets frontend (Vite)
# ---------------------------------------------------------------------------
FROM node:24-bookworm-slim AS assets

WORKDIR /app

ARG VITE_APP_NAME=Zaiko
ENV VITE_APP_NAME=${VITE_APP_NAME}

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
# Etapa 2: base PHP 8.5 con las extensiones que usa el proyecto
# ---------------------------------------------------------------------------
FROM php:8.5-fpm-bookworm AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libonig-dev \
        libsqlite3-dev \
        libsodium-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Cada extensión se compila en su propia invocación: en PHP 8.5 el build limpia
# `modules/` al terminar cada extensión, así que compilar varias de una sola vez
# rompe el `make install` final ("cp: cannot stat 'modules/*'").
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && for extension in \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pdo_pgsql \
        pdo_sqlite \
        pgsql \
        sodium \
        sqlite3 \
        zip; \
    do \
        docker-php-ext-install "$extension"; \
    done

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Etapa 3: dependencias PHP (sin dev)
# ---------------------------------------------------------------------------
FROM php-base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_NO_INTERACTION=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev

# ---------------------------------------------------------------------------
# Etapa 4: runtime (php-fpm + nginx + supervisor)
# ---------------------------------------------------------------------------
FROM php-base AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        postgresql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /var/www/html /var/www/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-zaico.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-zaico.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && rm -f /etc/nginx/sites-enabled/default \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
