# syntax=docker/dockerfile:1.7
# =============================================================================
#  Irmãos da Bola Academy — imagens de produção
#    target "api"  -> PHP-FPM 8.3 (API) — também usado pelo agendador (cron)
#    target "web"  -> Caddy (HTTPS automático) + SPA Vue compilada
# =============================================================================

# ---------- Dependências PHP (sem pacotes de desenvolvimento) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --ignore-platform-reqs --no-autoloader
# O mapa de classes precisa do código-fonte (modo autoritativo desliga a busca PSR-4)
COPY backend/src ./src
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------- Build do frontend ----------
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

# ---------- API (PHP-FPM) ----------
FROM php:8.3-fpm-alpine AS api
RUN apk add --no-cache libzip icu-libs mariadb-client mariadb-connector-c tzdata supercronic \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev icu-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql zip intl opcache \
 && apk del .build-deps \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php.ini      /usr/local/etc/php/conf.d/zz-iba.ini
COPY docker/php/www.conf     /usr/local/etc/php-fpm.d/zz-iba.conf
WORKDIR /app
COPY --chown=www-data:www-data backend/ ./
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
# Código de desenvolvimento não vai para produção
RUN rm -rf tests phpunit.xml phpstan.neon .env .env.example .phpunit.cache \
 && mkdir -p storage/logs /backups && chown -R www-data:www-data storage /backups && chmod 700 /backups
COPY docker/scheduler/crontab   /etc/iba/crontab
COPY docker/scheduler/backup.sh /usr/local/bin/iba-backup
COPY docker/scheduler/entrypoint.sh /usr/local/bin/iba-scheduler
RUN chmod 755 /usr/local/bin/iba-backup /usr/local/bin/iba-scheduler
USER www-data
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

# ---------- Web (Caddy + SPA) ----------
FROM caddy:2-alpine AS web
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=frontend /app/dist /srv
