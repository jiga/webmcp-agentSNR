#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [[ "${1:-}" != "--yes" ]]; then
  echo "This deletes only the Docker volumes owned by the wp-webmcp-agentsnr Compose project."
  echo "Re-run with --yes to continue." >&2
  exit 2
fi

cd "${REPO_DIR}"
docker compose down --volumes --remove-orphans
"${SCRIPT_DIR}/start-demo.sh"
