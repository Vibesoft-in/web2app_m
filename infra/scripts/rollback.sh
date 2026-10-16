#!/usr/bin/env bash
# ==============================================================================
# Appify Production Rollback Runbook Script
# Restores previous container state, flushes stale caches, and resets workers.
# Usage: ./rollback.sh [--rollback-migrations]
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-http://localhost/health}"

ROLLBACK_MIGRATIONS="${1:-}"

echo "======================================================================"
echo "⚠️  EMERGENCY ROLLBACK INITIATED: $(date -Iseconds)"
echo "======================================================================"

cd "${PROJECT_ROOT}"

# 1. Roll back database migrations if explicitly requested
if [[ "${ROLLBACK_MIGRATIONS}" == "--rollback-migrations" ]]; then
    echo "[STEP 1/5] Rolling back last database migration batch..."
    docker compose -f infra/docker-compose.yml run --rm php php artisan migrate:rollback --step=1 --force
else
    echo "[STEP 1/5] Skipping database migration rollback (pass --rollback-migrations to revert)."
fi

# 2. Flush corrupted or stale caches
echo "[STEP 2/5] Flushing application caches..."
docker compose -f infra/docker-compose.yml run --rm php php artisan cache:clear || true
docker compose -f infra/docker-compose.yml run --rm php php artisan config:clear || true
docker compose -f infra/docker-compose.yml run --rm php php artisan route:clear || true
docker compose -f infra/docker-compose.yml run --rm php php artisan view:clear || true

# 3. Recache with baseline configuration
echo "[STEP 3/5] Re-caching baseline configuration..."
docker compose -f infra/docker-compose.yml run --rm php php artisan config:cache
docker compose -f infra/docker-compose.yml run --rm php php artisan route:cache

# 4. Restart supervisor and Horizon
echo "[STEP 4/5] Terminating Horizon workers and restarting application services..."
docker compose -f infra/docker-compose.yml exec -T php php artisan horizon:terminate || true
docker compose -f infra/docker-compose.yml restart php dashboard nginx

# 5. Verify health check
echo "[STEP 5/5] Checking service recovery on ${HEALTHCHECK_URL}..."
sleep 5
HTTP_CODE=$(curl -s -o /tmp/rollback_health.json -w "%{http_code}" "${HEALTHCHECK_URL}" || echo "000")

if [[ "${HTTP_CODE}" == "200" ]]; then
    echo "✅ Rollback verified: System health is restored (HTTP 200)."
    cat /tmp/rollback_health.json
else
    echo "❌ CRITICAL: Service remains degraded (HTTP ${HTTP_CODE}) following rollback!" >&2
    exit 1
fi

echo "======================================================================"
echo "🛡️  Rollback procedure completed."
echo "======================================================================"
