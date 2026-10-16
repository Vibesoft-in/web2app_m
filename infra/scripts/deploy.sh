#!/usr/bin/env bash
# ==============================================================================
# Appify Zero-Downtime Production Deployment Script
# Orchestrates zero-downtime rolling deploys, cache warming, and health checks.
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

DEPLOY_START=$(date +%s)
HEALTHCHECK_URL="${HEALTHCHECK_URL:-http://localhost/health}"
MAX_HEALTH_ATTEMPTS=15
HEALTH_INTERVAL_SECS=3

echo "======================================================================"
echo "🚀 Appify Production Deployment Started: $(date -Iseconds)"
echo "Project Root: ${PROJECT_ROOT}"
echo "======================================================================"

cd "${PROJECT_ROOT}"

# 1. Pre-flight sanity checks
if [[ ! -f "backend/.env" ]]; then
    echo "[ERROR] backend/.env file not found! Deployment aborted." >&2
    exit 1
fi

# Ensure APP_DEBUG is false in production environment
if grep -q "APP_DEBUG=true" backend/.env; then
    echo "[CRITICAL] APP_DEBUG=true detected in backend/.env! Production deployment rejected." >&2
    exit 1
fi

echo "[STEP 1/6] Building production Docker images..."
docker compose -f infra/docker-compose.yml build --pull php dashboard

echo "[STEP 2/6] Executing database migrations (--force)..."
docker compose -f infra/docker-compose.yml run --rm php php artisan migrate --force

echo "[STEP 3/6] Warming Laravel production caches..."
docker compose -f infra/docker-compose.yml run --rm php php artisan config:cache
docker compose -f infra/docker-compose.yml run --rm php php artisan route:cache
docker compose -f infra/docker-compose.yml run --rm php php artisan view:cache
docker compose -f infra/docker-compose.yml run --rm php php artisan event:cache

echo "[STEP 4/6] Performing rolling reload of application containers..."
docker compose -f infra/docker-compose.yml up -d --no-deps php dashboard

echo "[STEP 5/6] Gracefully cycling Horizon queue workers..."
docker compose -f infra/docker-compose.yml exec -T php php artisan horizon:terminate || true

echo "[STEP 6/6] Reloading Nginx reverse proxy configuration..."
docker compose -f infra/docker-compose.yml exec -T nginx nginx -s reload || true

# 7. Post-deployment Automated Health Verification
echo "Verifying health endpoint at ${HEALTHCHECK_URL}..."
HEALTH_SUCCESS=false

for attempt in $(seq 1 ${MAX_HEALTH_ATTEMPTS}); do
    echo "Health check attempt ${attempt}/${MAX_HEALTH_ATTEMPTS}..."
    HTTP_CODE=$(curl -s -o /tmp/health_response.json -w "%{http_code}" "${HEALTHCHECK_URL}" || echo "000")
    
    if [[ "${HTTP_CODE}" == "200" ]]; then
        if grep -q '"status":"healthy"' /tmp/health_response.json 2>/dev/null; then
            HEALTH_SUCCESS=true
            echo "✅ Health check PASSED! Subsystems (Database, Redis, Storage) are operational."
            break
        fi
    fi
    sleep "${HEALTH_INTERVAL_SECS}"
done

if [[ "${HEALTH_SUCCESS}" != "true" ]]; then
    echo "❌ [DEPLOYMENT FAILED] Health check failed after ${MAX_HEALTH_ATTEMPTS} attempts!" >&2
    if [[ -f /tmp/health_response.json ]]; then
        cat /tmp/health_response.json >&2
    fi
    echo "Initiating automatic rollback..." >&2
    "${SCRIPT_DIR}/rollback.sh"
    exit 1
fi

DEPLOY_DURATION=$(( $(date +%s) - DEPLOY_START ))
echo "======================================================================"
echo "🎉 Appify Production Deployment SUCCESSFUL in ${DEPLOY_DURATION}s"
echo "======================================================================"
