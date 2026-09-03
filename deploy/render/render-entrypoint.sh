#!/usr/bin/env bash
set -Eeuo pipefail

umask 027

readonly WP_PATH="/var/www/html"
readonly WP_CLI_HOME="/tmp/agent-snr-wp-cli"
readonly DB_WAIT_ATTEMPTS=90
readonly DB_WAIT_SECONDS=2

required_environment=(
  WORDPRESS_DB_HOST
  WORDPRESS_DB_NAME
  WORDPRESS_DB_USER
  WORDPRESS_DB_PASSWORD
  WORDPRESS_AUTH_KEY
  WORDPRESS_SECURE_AUTH_KEY
  WORDPRESS_LOGGED_IN_KEY
  WORDPRESS_NONCE_KEY
  WORDPRESS_AUTH_SALT
  WORDPRESS_SECURE_AUTH_SALT
  WORDPRESS_LOGGED_IN_SALT
  WORDPRESS_NONCE_SALT
  WMCP_ADMIN_USER
  WMCP_ADMIN_PASSWORD
  WMCP_ADMIN_EMAIL
  WMCP_REPOSITORY_URL
)

for variable_name in "${required_environment[@]}"; do
  if [[ -z "${!variable_name:-}" ]]; then
    echo "Required Render setting is missing: ${variable_name}" >&2
    exit 1
  fi
done

public_url="${WMCP_PUBLIC_URL:-${RENDER_EXTERNAL_URL:-}}"
public_url="${public_url%/}"
if [[ ! "${public_url}" =~ ^https://[A-Za-z0-9._:-]+$ ]]; then
  echo "WMCP_PUBLIC_URL or RENDER_EXTERNAL_URL must be an HTTPS origin without a path." >&2
  exit 1
fi
export WMCP_PUBLIC_URL="${public_url}"

if [[ ! "${WMCP_REPOSITORY_URL}" =~ ^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]]; then
  echo "WMCP_REPOSITORY_URL must be an HTTPS GitHub repository URL without credentials, a query, or a fragment." >&2
  exit 1
fi

cd "${WP_PATH}"
docker-ensure-installed.sh true

install -d -o www-data -g www-data -m 0750 "${WP_CLI_HOME}" "${WP_CLI_HOME}/cache"
install -d -o www-data -g www-data -m 0775 "${WP_PATH}/wp-content/uploads"
chown -R www-data:www-data "${WP_PATH}/wp-content/uploads"

wp_cli() {
  runuser -u www-data -- env \
    HOME="${WP_CLI_HOME}" \
    WP_CLI_CACHE_DIR="${WP_CLI_HOME}/cache" \
    wp --path="${WP_PATH}" "$@"
}

database_ready() {
  runuser -u www-data -- php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = @mysqli_connect(
        (string) getenv("WORDPRESS_DB_HOST"),
        (string) getenv("WORDPRESS_DB_USER"),
        (string) getenv("WORDPRESS_DB_PASSWORD"),
        (string) getenv("WORDPRESS_DB_NAME")
    );
    if (false === $connection) {
        exit(1);
    }
    mysqli_close($connection);
  ' >/dev/null 2>&1
}

echo "Waiting for the private MariaDB service."
for ((attempt = 1; attempt <= DB_WAIT_ATTEMPTS; attempt++)); do
  if database_ready; then
    break
  fi

  if ((attempt == DB_WAIT_ATTEMPTS)); then
    echo "MariaDB was not ready before the bounded startup deadline." >&2
    exit 1
  fi

  sleep "${DB_WAIT_SECONDS}"
done

if ! wp_cli core is-installed >/dev/null 2>&1; then
  printf '%s\n' "${WMCP_ADMIN_PASSWORD}" | wp_cli core install \
    --url="${public_url}" \
    --title="Agent SNR Demo Store" \
    --admin_user="${WMCP_ADMIN_USER}" \
    --admin_email="${WMCP_ADMIN_EMAIL}" \
    --skip-email \
    --prompt=admin_password
fi

wp_cli option update home "${public_url}"
wp_cli option update siteurl "${public_url}"
wp_cli option update wmcp_agentsnr_repository_url "${WMCP_REPOSITORY_URL}"
wp_cli plugin activate woocommerce
wp_cli plugin activate wmcp-agentsnr
wp_cli eval-file /opt/agent-snr/bin/seed-demo.php
wp_cli option update woocommerce_coming_soon no
wp_cli option update woocommerce_store_pages_only no
wp_cli rewrite structure '/%postname%/' --hard

verify_version() {
  local component="$1"
  local expected="$2"
  local actual="$3"

  if [[ "${actual}" != "${expected}" ]]; then
    echo "${component} version check failed: expected ${expected}, found ${actual}." >&2
    exit 1
  fi
}

verify_version "WordPress" "${WMCP_WORDPRESS_VERSION}" "$(wp_cli core version)"
verify_version "WooCommerce" "${WMCP_WOOCOMMERCE_VERSION}" "$(wp_cli plugin get woocommerce --field=version)"
verify_version "Agent SNR" "${WMCP_AGENTSNR_VERSION}" "$(wp_cli plugin get wmcp-agentsnr --field=version)"
wp_cli plugin is-active woocommerce
wp_cli plugin is-active wmcp-agentsnr

echo "Agent SNR bootstrap completed; starting Apache."
exec /usr/local/bin/docker-entrypoint.sh "$@"
