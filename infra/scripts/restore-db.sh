#!/usr/bin/env bash
# ==============================================================================
# Appify Production Database Restore Script
# Restores a compressed MySQL dump archive (.sql.gz) with safety checks.
# Usage: ./restore-db.sh <path_to_backup_file.sql.gz> [--force]
# ==============================================================================

set -euo pipefail

BACKUP_FILE="${1:-}"
FORCE_FLAG="${2:-}"

if [[ -z "${BACKUP_FILE}" ]]; then
    echo "Usage: $0 <path_to_backup_file.sql.gz> [--force]" >&2
    exit 1
fi

if [[ ! -f "${BACKUP_FILE}" ]]; then
    echo "[ERROR] Backup file '${BACKUP_FILE}' does not exist!" >&2
    exit 1
fi

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-appify}"
DB_USERNAME="${DB_USERNAME:-appify}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [[ -z "${DB_PASSWORD}" ]]; then
    echo "[ERROR] DB_PASSWORD environment variable is not set." >&2
    exit 1
fi

echo "======================================================================"
echo "⚠️  WARNING: PRODUCTION DATABASE RESTORE INITIATED"
echo "Target Host:     ${DB_HOST}:${DB_PORT}"
echo "Target Database: ${DB_DATABASE}"
echo "Backup File:     ${BACKUP_FILE}"
echo "======================================================================"

if [[ "${FORCE_FLAG}" != "--force" && "${FORCE_FLAG}" != "-y" ]]; then
    read -rp "Are you absolutely sure you want to OVERWRITE '${DB_DATABASE}'? (type 'RESTORE' to proceed): " CONFIRM
    if [[ "${CONFIRM}" != "RESTORE" ]]; then
        echo "[ABORTED] Database restore cancelled by user."
        exit 0
    fi
fi

# Verify archive integrity before attempting restore
echo "[INFO] [$(date -Iseconds)] Verifying gzip integrity of backup archive..."
if ! gzip -t "${BACKUP_FILE}"; then
    echo "[ERROR] Corrupt archive! Integrity check failed." >&2
    exit 1
fi

export MYSQL_PWD="${DB_PASSWORD}"

echo "[INFO] [$(date -Iseconds)] Starting database restoration..."

# Stream decompression directly into mysql client with foreign key checks handling
{
    echo "SET FOREIGN_KEY_CHECKS=0;"
    echo "SET UNIQUE_CHECKS=0;"
    echo "SET AUTOCOMMIT=0;"
    gzip -dc "${BACKUP_FILE}"
    echo "COMMIT;"
    echo "SET FOREIGN_KEY_CHECKS=1;"
    echo "SET UNIQUE_CHECKS=1;"
} | mysql \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USERNAME}" \
    --max-allowed-packet=512M \
    "${DB_DATABASE}"

echo "[INFO] [$(date -Iseconds)] Database restored successfully from ${BACKUP_FILE}."

# Post-restore check: count tables
TABLE_COUNT=$(mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USERNAME}" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_DATABASE}';")
echo "[INFO] [$(date -Iseconds)] Restoration verification: ${TABLE_COUNT} tables present in database '${DB_DATABASE}'."
