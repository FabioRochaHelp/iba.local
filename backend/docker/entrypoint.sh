#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    echo "[entrypoint] .env não encontrado, copiando .env.example"
    cp .env.example .env
fi

# Com bind-mount do código em dev, o volume nomeado do vendor pode chegar
# vazio na primeira vez: reinstala as dependências se faltar o autoload.
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Instalando dependências do Composer..."
    composer install --no-interaction --no-progress --prefer-dist
fi

mkdir -p storage/logs
chown -R www-data:www-data storage

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
echo "[entrypoint] Aguardando MySQL em ${DB_HOST}:${DB_PORT}..."
for i in $(seq 1 60); do
    if php -r "exit(@fsockopen('${DB_HOST}', ${DB_PORT}) ? 0 : 1);"; then
        break
    fi
    sleep 1
done

echo "[entrypoint] Aplicando migrations..."
php database/migrate.php --seed || echo "[entrypoint] Aviso: migrate/seed falhou (verifique as credenciais em .env)"

exec "$@"
