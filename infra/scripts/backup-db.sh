#!/usr/bin/env bash
# ==============================================================================
# Appify Production Database Backup Script
# Performs single-transaction atomic mysqldump, gzip compression, and retention pruning.
# Usage: ./backup-db.sh [backup_dir]
# ==============================================================================

set -euo pipefail

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="${1:-${BACKUP_DIR:-/var/backups/appify/mysql}}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-appify}"
DB_USERNAME="${DB_USERNAME:-appify}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [[ -z "${DB_PASSWORD}" ]]; then
    echo "[ERROR] DB_PASSWORD environment variable is not set." >&2
    exit 1
fi

mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

BACKUP_FILE="${BACKUP_DIR}/${DB_DATABASE}_${TIMESTAMP}.sql.gz"
TEMP_FILE="${BACKUP_FILE}.tmp"

echo "[INFO] [$(date -Iseconds)] Starting database backup for database: '${DB_DATABASE}' at ${DB_HOST}:${DB_PORT}..."

# Export MYSQL_PWD to avoid password exposure in ps/process lists
export MYSQL_PWD="${DB_PASSWORD}"

# Perform dump with single transaction, quick streaming, routines, triggers
mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USERNAME}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --set-gtid-purged=OFF \
    --no-autocommit \
    "${DB_DATABASE}" | gzip -9 > "${TEMP_FILE}"

mv "${TEMP_FILE}" "${BACKUP_FILE}"
chmod 600 "${BACKUP_FILE}"

FILESIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
echo "[INFO] [$(date -Iseconds)] Backup completed successfully: ${BACKUP_FILE} (Size: ${FILESIZE})"

# Verify gzip integrity
if gzip -t "${BACKUP_FILE}"; then
    echo "[INFO] [$(date -Iseconds)] Backup archive integrity verified."
else
    echo "[ERROR] [$(date -Iseconds)] Backup archive failed integrity test!" >&2
    exit 1
fi

# Retention cleanup: delete backups older than RETENTION_DAYS
echo "[INFO] [$(date -Iseconds)] Pruning backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_DIR}" -type f -name "${DB_DATABASE}_*.sql.gz" -mtime +"${RETENTION_DAYS}" -exec rm -f {} \;

echo "[INFO] [$(date -Iseconds)] Database backup pipeline finished successfully."
