#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

docker run --rm \
  --volume "${REPO_DIR}:/workspace:ro" \
  --workdir /workspace \
  php:8.1-cli \
  sh -c 'find plugin tests bin -type f -name "*.php" -print0 | xargs -0 -n1 php -l'
