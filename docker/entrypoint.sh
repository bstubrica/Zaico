#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Directorios de escritura (también cuando se monta el volumen `storage`).
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/app/snipec \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

# APP_KEY persistente: se guarda en el volumen `storage` para no regenerarla
# en cada reinicio (lo que invalidaría sesiones y datos cifrados).
if [ -z "${APP_KEY:-}" ]; then
    key_file="storage/.app_key"

    if [ -s "$key_file" ]; then
        APP_KEY="$(cat "$key_file")"
    else
        APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
        printf '%s' "$APP_KEY" > "$key_file"
        chmod 600 "$key_file"
    fi

    export APP_KEY
fi

# El healthcheck de compose ya espera a PostgreSQL, pero en un reinicio el
# contenedor puede adelantarse. `pg_isready` viene de postgresql-client.
if [ "${DB_CONNECTION:-pgsql}" = "pgsql" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Esperando a PostgreSQL en ${DB_HOST:-db}:${DB_PORT:-5432}..."
    for intento in $(seq 1 60); do
        if pg_isready \
            -h "${DB_HOST:-db}" \
            -p "${DB_PORT:-5432}" \
            -U "${DB_USERNAME:-}" \
            -d "${DB_DATABASE:-}" >/dev/null 2>&1; then
            break
        fi

        if [ "$intento" -eq 60 ]; then
            echo "ERROR: PostgreSQL no respondió a tiempo." >&2
            exit 1
        fi

        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Ejecutando migraciones..."
    migrado=0

    for intento in $(seq 1 30); do
        if php artisan migrate --force; then
            migrado=1
            break
        fi

        echo "Migraciones fallaron; reintentando en 2s ($intento/30)..." >&2
        sleep 2
    done

    if [ "$migrado" -ne 1 ]; then
        echo "ERROR: no se pudieron aplicar las migraciones." >&2
        exit 1
    fi

    if [ "${RUN_SEEDERS:-true}" = "true" ]; then
        # Solo siembra una base vacía: UserSeeder vuelve a fijar las claves
        # de los usuarios demo y no debe ejecutarse en cada reinicio.
        if ! php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(\App\Models\User::query()->exists() ? 0 : 1);'; then
            echo "Sembrando datos iniciales..."
            php artisan db:seed --force
        fi
    fi
fi

# Enlaces y cachés de Laravel.
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
