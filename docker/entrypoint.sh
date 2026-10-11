#!/usr/bin/env bash
set -e

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Ejecutando migraciones (con reintentos)..."
    for intento in $(seq 1 30); do
        if php artisan migrate --force; then
            break
        fi
        echo "Base de datos no disponible, reintentando en 2s ($intento/30)..."
        sleep 2
    done
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

# Asegura que los procesos web puedan escribir (nginx usa www-data).
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
