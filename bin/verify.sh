#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

cd "${REPO_DIR}"
"${SCRIPT_DIR}/lint-php.sh"
docker run --rm \
  --volume "${REPO_DIR}:/app" \
  --workdir /app \
  composer:2.8 \
  composer verify
npm run verify
"${SCRIPT_DIR}/build-playground-bundle.sh"
