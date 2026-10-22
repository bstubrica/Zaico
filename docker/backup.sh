#!/usr/bin/env bash
#
# Respaldo automático de PostgreSQL (Fase 11).
# Pensado para ejecutarse vía cron/sidecar. Requiere: pg_dump en el PATH.
#
# Variables esperadas: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.
# Opcional: BACKUP_DIR (por defecto /backups), BACKUP_RETENCION_DIAS (14).
#
set -euo pipefail

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
BACKUP_DIR="${BACKUP_DIR:-/backups}"
RETENCION_DIAS="${BACKUP_RETENCION_DIAS:-14}"

mkdir -p "$BACKUP_DIR"

stamp="$(date +%Y%m%d_%H%M%S)"
destino="$BACKUP_DIR/zaico_${stamp}.dump"

export PGPASSWORD="${DB_PASSWORD:-}"

echo "Esperando a PostgreSQL en ${DB_HOST}:${DB_PORT}..."
for intento in $(seq 1 60); do
    if pg_isready --host="$DB_HOST" --port="$DB_PORT" --username="${DB_USERNAME}" --dbname="${DB_DATABASE}" >/dev/null 2>&1; then
        break
    fi

    if [ "$intento" -eq 60 ]; then
        echo "ERROR: PostgreSQL no respondió a tiempo." >&2
        exit 1
    fi

    sleep 2
done

echo "Respaldando ${DB_DATABASE} en ${destino}..."
pg_dump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="${DB_USERNAME}" \
    --no-owner \
    --format=custom \
    --file="$destino" \
    "${DB_DATABASE}"

echo "Eliminando respaldos con más de ${RETENCION_DIAS} días..."
find "$BACKUP_DIR" -name 'zaico_*.dump' -type f -mtime "+${RETENCION_DIAS}" -delete

echo "Respaldo completado."
