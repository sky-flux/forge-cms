ARG PHP_VERSION=8.5.5
ARG FRANKENPHP_VERSION=1.12.2

# ============================================
# Stage 1: base
# ============================================
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-bookworm AS base

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        curl \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        redis \
        intl \
        zip \
        gd \
        bcmath \
        opcache \
        pcntl \
        exif \
        sockets

COPY --from=composer:2.9.7 /usr/bin/composer /usr/bin/composer

RUN groupadd --system --gid 1000 app \
    && useradd --system --uid 1000 --gid app --home /app --shell /bin/bash app \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R app:app /app /data /config

# ============================================
# Stage 2: dev
# ============================================
FROM base AS dev

RUN install-php-extensions xdebug

COPY deploy/php.dev.ini /usr/local/etc/php/conf.d/app.ini
COPY deploy/Caddyfile.dev /etc/frankenphp/Caddyfile

USER app

# ============================================
# Stage 3: prod
# 前端资产须在宿主机 `bun run build` 后再进入 build context
# ============================================
FROM base AS prod

COPY deploy/php.prod.ini /usr/local/etc/php/conf.d/app.ini
COPY deploy/Caddyfile.prod /etc/frankenphp/Caddyfile

COPY --chown=app:app composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY --chown=app:app . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && php artisan optimize \
    && chown -R app:app storage bootstrap/cache

USER app

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80", "--workers=auto"]
