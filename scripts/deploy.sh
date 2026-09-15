#!/usr/bin/env bash
set -euo pipefail
: "${1:?Pass the immutable application image tag}"
: "${DEPLOY_DIR:?Set the deployment checkout directory}"
: "${HEALTH_URL:=http://127.0.0.1:8080/health}"
cd "$DEPLOY_DIR"
export APP_IMAGE="$1"
docker compose pull api queue scheduler
docker compose run --rm --no-deps api php artisan migrate --force
docker compose up -d --no-build api queue scheduler nginx
for attempt in $(seq 1 30); do
    if curl --fail --silent --show-error "$HEALTH_URL" >/dev/null; then
        printf 'Deployment healthy: %s\n' "$APP_IMAGE"
        exit 0
    fi
    sleep 2
done
printf 'Deployment health check failed. Inspect logs and roll back to the previous image.\n' >&2
exit 1
