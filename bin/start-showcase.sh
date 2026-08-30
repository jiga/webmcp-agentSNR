#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE_FILE="${REPO_DIR}/docker-compose.yml"
PROJECT_NAME="agent-snr-showcase"
HTTP_PORT="${WMCP_SHOWCASE_PORT:-18084}"
SITE_URL="http://localhost:${HTTP_PORT}"
WOO_VERSION="${WMCP_SHOWCASE_WOOCOMMERCE_VERSION:-11.0.1}"
RUNTIME_ROOT="${REPO_DIR}/.release-test/showcase-runtime"
CREDENTIALS_FILE="${RUNTIME_ROOT}/operator-credentials"
STATE_FILE="${RUNTIME_ROOT}/active-artifact"
PLUGIN_DIR="${RUNTIME_ROOT}/compose-placeholder"
PLUGIN_ARCHIVE=""
PLUGIN_SHA256=""
EXTRACTION_TMP=""
ARTIFACT_PREPARED=0

cleanup_temp() {
  if [[ -n "${EXTRACTION_TMP}" && "${EXTRACTION_TMP}" == "${RUNTIME_ROOT}/"*.tmp.* ]]; then
    rm -rf -- "${EXTRACTION_TMP}"
  fi
}
trap cleanup_temp EXIT

usage() {
  cat <<'USAGE'
Usage: ./bin/start-showcase.sh [start|status|verify|stop|credentials]
       ./bin/start-showcase.sh reset --confirm-agent-snr-showcase

Commands:
  start        Start or refresh the isolated showcase (default).
  status       Show only the showcase containers and local URLs.
  verify       Check the release artifact, services, pages, and seeded catalog.
  stop         Stop the showcase without deleting its data.
  credentials  Print the generated local-only operator credentials.
  reset        Delete only agent-snr-showcase volumes, then start clean.

Environment:
  WMCP_SHOWCASE_PORT                 Local HTTP port (default: 18084).
  WMCP_SHOWCASE_ZIP                  Exact release ZIP to run.
  WMCP_SHOWCASE_ADMIN_USER           Optional operator user for first setup.
  WMCP_SHOWCASE_ADMIN_PASSWORD       Optional operator password for first setup.
  WMCP_SHOWCASE_WOOCOMMERCE_VERSION  WooCommerce version (default: 11.0.1).

If a clean clone has no plugin ZIP in dist/, start builds the deterministic
release ZIP and checksum from the current checkout before launching it.
USAGE
}

fail() {
  echo "Showcase error: $*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

validate_configuration() {
  [[ "${HTTP_PORT}" =~ ^[1-9][0-9]{0,4}$ ]] || fail "WMCP_SHOWCASE_PORT must be an integer from 1 through 65535."
  (( HTTP_PORT <= 65535 )) || fail "WMCP_SHOWCASE_PORT must be an integer from 1 through 65535."
  [[ "${WOO_VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "WMCP_SHOWCASE_WOOCOMMERCE_VERSION must be an exact semantic version."
}

compose() {
  COMPOSE_PROJECT_NAME="${PROJECT_NAME}" \
  WMCP_BIND_HOST="127.0.0.1" \
  WMCP_HTTP_PORT="${HTTP_PORT}" \
  WMCP_PLUGIN_DIR="${PLUGIN_DIR}" \
    docker compose --project-name "${PROJECT_NAME}" --file "${COMPOSE_FILE}" "$@"
}

build_release_archive_if_missing() {
  local candidate
  local -a candidates=()

  if [[ -n "${WMCP_SHOWCASE_ZIP:-}" ]]; then
    return 0
  fi

  shopt -s nullglob
  for candidate in "${REPO_DIR}"/dist/wmcp-agentops-*.zip; do
    [[ "$(basename "${candidate}")" == *-playground-* ]] || candidates+=("${candidate}")
  done
  shopt -u nullglob

  if [[ "${#candidates[@]}" -eq 0 ]]; then
    require_command rsync
    require_command zip
    echo "No plugin release ZIP found; building the deterministic artifact from this checkout."
    "${SCRIPT_DIR}/build-plugin.sh"
  fi
}

wp_cli() {
  compose run --rm cli wp "$@"
}

resolve_release_archive() {
  local candidate
  local requested="${WMCP_SHOWCASE_ZIP:-}"
  local -a candidates=()

  if [[ -n "${requested}" ]]; then
    if [[ "${requested}" != /* ]]; then
      requested="${REPO_DIR}/${requested}"
    fi
    candidates=("${requested}")
  else
    shopt -s nullglob
    for candidate in "${REPO_DIR}"/dist/wmcp-agentops-*.zip; do
      [[ "$(basename "${candidate}")" == *-playground-* ]] || candidates+=("${candidate}")
    done
    shopt -u nullglob
  fi

  if [[ "${#candidates[@]}" -ne 1 ]]; then
    fail "Expected one plugin release ZIP in dist/. Run start to build a missing artifact, or set WMCP_SHOWCASE_ZIP to choose exactly one."
  fi

  [[ -f "${candidates[0]}" ]] || fail "Release ZIP not found: ${candidates[0]}"
  PLUGIN_ARCHIVE="$(cd "$(dirname "${candidates[0]}")" && pwd)/$(basename "${candidates[0]}")"
}

verify_release_archive() {
  local checksum_file="${PLUGIN_ARCHIVE}.sha256"
  local expected

  [[ -f "${checksum_file}" ]] || fail "Checksum sidecar not found: ${checksum_file}"
  expected="$(awk 'NR == 1 { print $1 }' "${checksum_file}")"
  [[ "${expected}" =~ ^[[:xdigit:]]{64}$ ]] || fail "Checksum sidecar is malformed: ${checksum_file}"
  expected="$(printf '%s' "${expected}" | tr '[:upper:]' '[:lower:]')"

  PLUGIN_SHA256="$(shasum -a 256 "${PLUGIN_ARCHIVE}" | awk '{ print $1 }')"
  [[ "${PLUGIN_SHA256}" == "${expected}" ]] || fail "Release ZIP checksum does not match its sidecar."

  if ! unzip -Z1 "${PLUGIN_ARCHIVE}" | awk '
    BEGIN { count = 0; bad = 0 }
    {
      count++
      if ($0 !~ /^wmcp-agentops(\/[^\/]+)*\/?$/ || $0 ~ /(^|\/)\.\.?($|\/)/ || index($0, "\\") > 0) {
        bad = 1
      }
    }
    END { exit (count == 0 || bad) ? 1 : 0 }
  '; then
    fail "Release ZIP must contain only one safe top-level wmcp-agentops/ directory."
  fi

  if ! unzip -Z -l "${PLUGIN_ARCHIVE}" | awk '
    $1 ~ /^[bcdlps-][rwxstST-]{9}$/ {
      count++
      if (substr($1, 1, 1) != "-" && substr($1, 1, 1) != "d") {
        bad = 1
      }
    }
    END { exit (count == 0 || bad) ? 1 : 0 }
  '; then
    fail "Release ZIP contains an unsupported entry type; only regular files and directories are allowed."
  fi
}

prepare_release_plugin() {
  local repair_cache="${1:-false}"
  local extract_dir
  local marker
  local rejected_dir

  if [[ "${ARTIFACT_PREPARED}" -eq 1 ]]; then
    return 0
  fi

  resolve_release_archive
  verify_release_archive
  extract_dir="${RUNTIME_ROOT}/artifacts/${PLUGIN_SHA256}"
  marker="${extract_dir}/.artifact-sha256"

  mkdir -p "${RUNTIME_ROOT}/artifacts"
  EXTRACTION_TMP="${RUNTIME_ROOT}/artifacts/${PLUGIN_SHA256}.tmp.$$"
  mkdir -p "${EXTRACTION_TMP}"
  unzip -q "${PLUGIN_ARCHIVE}" -d "${EXTRACTION_TMP}"
  [[ -f "${EXTRACTION_TMP}/wmcp-agentops/wmcp-agentops.php" ]] || fail "The release ZIP is missing the plugin entry point."
  printf '%s\n' "${PLUGIN_SHA256}" > "${EXTRACTION_TMP}/.artifact-sha256"

  if [[ ! -d "${extract_dir}" ]]; then
    mv "${EXTRACTION_TMP}" "${extract_dir}"
    EXTRACTION_TMP=""
  elif find "${extract_dir}" -type l -print -quit | grep -q . \
    || ! diff -qr "${EXTRACTION_TMP}" "${extract_dir}" >/dev/null; then
    if [[ "${repair_cache}" != "true" ]]; then
      fail "The extracted release directory differs from the verified ZIP. Run start to replace only this artifact cache."
    fi
    rejected_dir="${extract_dir}.rejected.$$"
    mv "${extract_dir}" "${rejected_dir}"
    mv "${EXTRACTION_TMP}" "${extract_dir}"
    EXTRACTION_TMP=""
    echo "Replaced a modified artifact cache; the prior directory was retained at ${rejected_dir}." >&2
  else
    rm -rf -- "${EXTRACTION_TMP}"
    EXTRACTION_TMP=""
  fi

  [[ -f "${extract_dir}/wmcp-agentops/wmcp-agentops.php" ]] || fail "The extracted release directory is incomplete: ${extract_dir}"
  [[ -f "${marker}" ]] && [[ "$(sed -n '1p' "${marker}")" == "${PLUGIN_SHA256}" ]] \
    || fail "The extracted release directory failed its artifact marker check."

  PLUGIN_DIR="${extract_dir}/wmcp-agentops"
  printf 'archive=%s\nsha256=%s\nplugin_dir=%s\n' \
    "${PLUGIN_ARCHIVE}" "${PLUGIN_SHA256}" "${PLUGIN_DIR}" > "${STATE_FILE}"
  ARTIFACT_PREPARED=1
}

ensure_credentials() {
  local requested_user="${WMCP_SHOWCASE_ADMIN_USER:-}"
  local requested_password="${WMCP_SHOWCASE_ADMIN_PASSWORD:-}"
  local generated_password

  mkdir -p "${RUNTIME_ROOT}"
  if [[ -n "${requested_user}" || -n "${requested_password}" ]]; then
    [[ -n "${requested_user}" && -n "${requested_password}" ]] \
      || fail "Set both WMCP_SHOWCASE_ADMIN_USER and WMCP_SHOWCASE_ADMIN_PASSWORD."
    [[ "${requested_user}" =~ ^[A-Za-z0-9._-]{3,60}$ ]] || fail "The showcase operator username is invalid."
    [[ "${#requested_password}" -ge 16 && "${requested_password}" != *$'\n'* && "${requested_password}" != *$'\r'* ]] \
      || fail "The showcase operator password must be at least 16 characters with no line breaks."
    umask 077
    printf 'user=%s\npassword=%s\n' "${requested_user}" "${requested_password}" > "${CREDENTIALS_FILE}"
  elif [[ ! -f "${CREDENTIALS_FILE}" ]]; then
    generated_password="$(openssl rand -hex 18)"
    umask 077
    printf 'user=%s\npassword=%s\n' "wmcp-showcase-operator" "${generated_password}" > "${CREDENTIALS_FILE}"
  fi

  chmod 600 "${CREDENTIALS_FILE}"
}

read_credentials() {
  ADMIN_USER="$(sed -n 's/^user=//p' "${CREDENTIALS_FILE}")"
  ADMIN_PASSWORD="$(sed -n 's/^password=//p' "${CREDENTIALS_FILE}")"
  [[ -n "${ADMIN_USER}" && -n "${ADMIN_PASSWORD}" ]] || fail "The local operator credentials file is incomplete."
}

wait_for_wordpress() {
  local attempt

  for attempt in {1..45}; do
    if wp_cli core version >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done

  fail "WordPress files did not become ready."
}

provision_wordpress() {
  local installed_woo_version

  ensure_credentials
  read_credentials
  compose up -d database
  compose up -d --force-recreate wordpress
  wait_for_wordpress

  if ! wp_cli core is-installed >/dev/null 2>&1; then
    wp_cli core install \
      --url="${SITE_URL}" \
      --title="TrailForge Lab" \
      --admin_user="${ADMIN_USER}" \
      --admin_password="${ADMIN_PASSWORD}" \
      --admin_email="showcase-operator@example.invalid" \
      --skip-email
  elif wp_cli user get "${ADMIN_USER}" --field=ID >/dev/null 2>&1; then
    wp_cli user update "${ADMIN_USER}" --user_pass="${ADMIN_PASSWORD}" >/dev/null
  else
    wp_cli user create "${ADMIN_USER}" "showcase-operator@example.invalid" \
      --role=administrator --user_pass="${ADMIN_PASSWORD}" >/dev/null
  fi

  wp_cli option update home "${SITE_URL}" >/dev/null
  wp_cli option update siteurl "${SITE_URL}" >/dev/null
  wp_cli config set WP_ENVIRONMENT_TYPE local --type=constant >/dev/null
  wp_cli config set WMCP_AGENTOPS_DEMO_MODE true --raw --type=constant >/dev/null
  wp_cli config set WMCP_AGENTOPS_ALLOW_DESTRUCTIVE_RESET true --raw --type=constant >/dev/null
  installed_woo_version="$(wp_cli plugin get woocommerce --field=version 2>/dev/null || true)"
  if [[ "${installed_woo_version}" == "${WOO_VERSION}" ]]; then
    wp_cli plugin activate woocommerce
  else
    wp_cli plugin install woocommerce --version="${WOO_VERSION}" --activate --force
  fi
  wp_cli plugin activate wmcp-agentops
  wp_cli eval-file /workspace/bin/seed-demo.php
  wp_cli option update woocommerce_coming_soon no >/dev/null
  wp_cli option update woocommerce_store_pages_only no >/dev/null
  wp_cli rewrite structure '/%postname%/' >/dev/null
}

verify_page() {
  local path="$1"
  local expected="$2"
  local label="$3"
  local html
  local attempt

  for attempt in {1..10}; do
    if html="$(curl --fail --silent --show-error --max-time 15 "${SITE_URL}${path}" 2>/dev/null)" \
      && [[ "${html}" == *"${expected}"* ]]; then
      echo "  passed: ${label}"
      return 0
    fi
    sleep 1
  done

  fail "${label} did not return HTTP 200 with its expected release marker."
}

verify_showcase() {
  local installed_woo_version
  local product_count
  local running_services
  local wordpress_container
  local mounted_plugin

  prepare_release_plugin
  echo "Verifying Agent SNR showcase at ${SITE_URL}"
  running_services="$(compose ps --services --status running)"
  grep -qx 'database' <<< "${running_services}" || fail "The showcase database is not running. Start the showcase first."
  grep -qx 'wordpress' <<< "${running_services}" || fail "The showcase WordPress service is not running. Start the showcase first."
  compose ps --status running
  wordpress_container="$(compose ps -q wordpress)"
  mounted_plugin="$(docker inspect --format '{{range .Mounts}}{{if eq .Destination "/var/www/html/wp-content/plugins/wmcp-agentops"}}{{.Source}}{{end}}{{end}}' "${wordpress_container}")"
  [[ "${mounted_plugin}" == "${PLUGIN_DIR}" || "${mounted_plugin}" == "/host_mnt${PLUGIN_DIR}" ]] \
    || fail "WordPress is not mounted to the verified release artifact."
  wp_cli plugin is-active wmcp-agentops || fail "Agent SNR is not active."
  wp_cli plugin is-active woocommerce || fail "WooCommerce is not active."
  installed_woo_version="$(wp_cli plugin get woocommerce --field=version)"
  [[ "${installed_woo_version}" == "${WOO_VERSION}" ]] || fail "WooCommerce ${installed_woo_version} is active; expected ${WOO_VERSION}."
  [[ "$(wp_cli option get home)" == "${SITE_URL}" ]] || fail "WordPress home URL does not match the showcase port."
  [[ "$(wp_cli option get woocommerce_coming_soon)" == "no" ]] || fail "WooCommerce coming-soon mode is still enabled."
  [[ "$(wp_cli option get woocommerce_store_pages_only)" == "no" ]] || fail "WooCommerce store-only visibility is still enabled."
  product_count="$(wp_cli post list --post_type=product --post_status=publish --format=count)"
  [[ "${product_count}" == "12" ]] || fail "Expected 12 published showcase products; found ${product_count}."

  verify_page "/" "Agent SNR" "judge landing"
  verify_page "/storefront-demo/" "Agent-ready Storefront" "storefront"
  verify_page "/agentops-demo/" "Current browser evidence" "Agent SNR monitor"
  verify_page "/webmcp-health/" "WebMCP Readiness" "readiness page"
  echo "  passed: release-artifact mount"
  echo "  passed: artifact ${PLUGIN_SHA256}"
  echo "Showcase verification passed."
}

start_showcase() {
  build_release_archive_if_missing
  prepare_release_plugin true
  provision_wordpress
  verify_showcase

  echo
  echo "Agent SNR isolated showcase is ready:"
  echo "  Judge start: ${SITE_URL}/"
  echo "  Storefront:  ${SITE_URL}/storefront-demo/"
  echo "  Agent SNR:   ${SITE_URL}/agentops-demo/"
  echo "  Readiness:   ${SITE_URL}/webmcp-health/"
  echo "  Artifact:    $(basename "${PLUGIN_ARCHIVE}") (${PLUGIN_SHA256})"
  echo "  Operator credentials are local-only; request them with:"
  echo "    ./bin/start-showcase.sh credentials"
}

status_showcase() {
  echo "Agent SNR showcase project: ${PROJECT_NAME}"
  echo "Local URL: ${SITE_URL}/"
  if [[ -f "${STATE_FILE}" ]]; then
    echo "Last prepared artifact record:"
    sed 's/^/  /' "${STATE_FILE}"
  else
    echo "Last prepared artifact record: not created yet"
  fi
  compose ps
}

show_credentials() {
  ensure_credentials
  read_credentials
  echo "Local-only Agent SNR operator credentials"
  echo "  Admin URL: ${SITE_URL}/wp-admin/"
  echo "  User:      ${ADMIN_USER}"
  echo "  Password:  ${ADMIN_PASSWORD}"
}

stop_showcase() {
  compose down
  echo "Stopped ${PROJECT_NAME}; its database and WordPress volumes were preserved."
}

reset_showcase() {
  [[ "${1:-}" == "--confirm-agent-snr-showcase" ]] \
    || fail "Reset deletes only ${PROJECT_NAME} volumes. Re-run with: ./bin/start-showcase.sh reset --confirm-agent-snr-showcase"
  compose down --volumes --remove-orphans
  echo "Removed only ${PROJECT_NAME} containers, network, and named volumes."
  start_showcase
}

main() {
  local command_name="${1:-start}"

  cd "${REPO_DIR}"
  if [[ "${command_name}" != "-h" && "${command_name}" != "--help" && "${command_name}" != "help" ]]; then
    validate_configuration
  fi
  case "${command_name}" in
    start)
      [[ "$#" -eq 1 || "$#" -eq 0 ]] || { usage >&2; exit 2; }
      require_command docker
      require_command curl
      require_command diff
      require_command unzip
      require_command shasum
      require_command openssl
      start_showcase
      ;;
    status)
      [[ "$#" -eq 1 ]] || { usage >&2; exit 2; }
      require_command docker
      status_showcase
      ;;
    verify)
      [[ "$#" -eq 1 ]] || { usage >&2; exit 2; }
      require_command docker
      require_command curl
      require_command diff
      require_command unzip
      require_command shasum
      verify_showcase
      ;;
    stop)
      [[ "$#" -eq 1 ]] || { usage >&2; exit 2; }
      require_command docker
      stop_showcase
      ;;
    credentials)
      [[ "$#" -eq 1 ]] || { usage >&2; exit 2; }
      require_command openssl
      show_credentials
      ;;
    reset)
      [[ "$#" -eq 2 ]] || { usage >&2; exit 2; }
      require_command docker
      require_command curl
      require_command diff
      require_command unzip
      require_command shasum
      require_command openssl
      reset_showcase "${2}"
      ;;
    -h|--help|help)
      usage
      ;;
    *)
      usage >&2
      exit 2
      ;;
  esac
}

main "$@"
