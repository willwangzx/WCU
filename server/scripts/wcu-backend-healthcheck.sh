#!/usr/bin/env bash
set -euo pipefail

URL="${WCU_HEALTHCHECK_URL:-http://127.0.0.1/api/application.php}"
SERVICE_NAME="${WCU_HEALTHCHECK_SERVICE:-wcu-backend}"
MAX_TIME="${WCU_HEALTHCHECK_MAX_TIME:-5}"

if curl --fail --silent --show-error --max-time "$MAX_TIME" "$URL" >/dev/null; then
  exit 0
fi

logger -t wcu-backend-healthcheck "Healthcheck failed for ${URL}; restarting ${SERVICE_NAME}"
systemctl restart "${SERVICE_NAME}"
