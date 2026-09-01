#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
HTTP_PORT="${WMCP_HTTP_PORT:-18080}"
SITE_URL="http://localhost:${HTTP_PORT}"
ADMIN_USER="${WMCP_ADMIN_USER:-wmcp-admin}"
ADMIN_PASSWORD="${WMCP_ADMIN_PASSWORD:-local-demo-admin-password}"
ADMIN_EMAIL="${WMCP_ADMIN_EMAIL:-demo-admin@example.invalid}"
WOO_VERSION="${WMCP_WOOCOMMERCE_VERSION:-11.0.1}"

cd "${REPO_DIR}"
docker compose up -d database wordpress

for attempt in {1..30}; do
  if docker compose run --rm cli wp core version >/dev/null 2>&1; then
    break
  fi

  if [[ "${attempt}" -eq 30 ]]; then
    echo "WordPress files did not become ready." >&2
    exit 1
  fi

  sleep 2
done

if ! docker compose run --rm cli wp core is-installed >/dev/null 2>&1; then
  docker compose run --rm cli wp core install \
    --url="${SITE_URL}" \
    --title="TrailForge Lab" \
    --admin_user="${ADMIN_USER}" \
    --admin_password="${ADMIN_PASSWORD}" \
    --admin_email="${ADMIN_EMAIL}" \
    --skip-email
fi

docker compose run --rm cli wp option update home "${SITE_URL}"
docker compose run --rm cli wp option update siteurl "${SITE_URL}"
docker compose run --rm cli wp config set WP_ENVIRONMENT_TYPE local --type=constant
docker compose run --rm cli wp config set WMCP_AGENTOPS_DEMO_MODE true --raw --type=constant
docker compose run --rm cli wp config set WMCP_AGENTOPS_ALLOW_DESTRUCTIVE_RESET true --raw --type=constant
docker compose run --rm cli wp plugin install woocommerce --version="${WOO_VERSION}" --activate
docker compose run --rm cli wp plugin activate wmcp-agentops
docker compose run --rm cli wp eval-file /workspace/bin/seed-demo.php
docker compose run --rm cli wp option update woocommerce_coming_soon no
docker compose run --rm cli wp option update woocommerce_store_pages_only no
docker compose run --rm cli wp rewrite structure '/%postname%/'

echo
echo "Agent SNR demo is ready:"
echo "  Judge start: ${SITE_URL}/"
echo "  Storefront:  ${SITE_URL}/storefront-demo/"
echo "  Agent SNR:    ${SITE_URL}/agentops-demo/"
echo "  Readiness:   ${SITE_URL}/webmcp-health/"
echo "  Admin:       ${SITE_URL}/wp-admin/"
echo "  User:        ${ADMIN_USER}"
echo "  Password:    ${ADMIN_PASSWORD} (local demo only)"
