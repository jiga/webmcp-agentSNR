#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_SOURCE="${REPO_DIR}/plugin/wmcp-agentsnr"
DIST_DIR="${REPO_DIR}/dist"
BUILD_DIR="$(mktemp -d)"

cleanup() {
  rm -rf "${BUILD_DIR}"
}
trap cleanup EXIT

VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${PLUGIN_SOURCE}/wmcp-agentsnr.php" | head -n 1)"
if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9.-]+)?$ ]]; then
  echo "Unable to resolve a valid plugin version." >&2
  exit 1
fi

mkdir -p "${BUILD_DIR}/wmcp-agentsnr" "${DIST_DIR}"
rsync -a \
  --exclude='.DS_Store' \
  --exclude='*.map' \
  --exclude='vendor' \
  "${PLUGIN_SOURCE}/" "${BUILD_DIR}/wmcp-agentsnr/"

cp "${REPO_DIR}/LICENSE" "${BUILD_DIR}/wmcp-agentsnr/LICENSE"
if [[ -f "${REPO_DIR}/THIRD_PARTY_NOTICES.md" ]]; then
  cp "${REPO_DIR}/THIRD_PARTY_NOTICES.md" "${BUILD_DIR}/wmcp-agentsnr/THIRD_PARTY_NOTICES.md"
fi

if find "${BUILD_DIR}/wmcp-agentsnr" \
  \( -type d \( -name '.git' -o -name '.github' -o -name 'node_modules' -o -name 'tests' -o -name 'vendor' -o -name 'coverage' \) \
  -o -type f \( -name '.env*' -o -name '*.zip' -o -name '*.log' -o -name '*.map' -o -name 'composer.lock' -o -name 'package-lock.json' \) \) \
  | grep -q .; then
  echo "Unsafe development artifact found in plugin package." >&2
  exit 1
fi

if grep -RIlE -- \
  '-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|sk-(proj-)?[A-Za-z0-9_-]{20,}|gh[pousr]_[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|xox[baprs]-[A-Za-z0-9-]{10,}' \
  "${BUILD_DIR}/wmcp-agentsnr" >/dev/null; then
  echo "A secret-like credential was found in the plugin package." >&2
  exit 1
fi

find "${BUILD_DIR}/wmcp-agentsnr" -exec touch -t 198001010000 {} +

ARCHIVE="${DIST_DIR}/wmcp-agentsnr-${VERSION}.zip"
rm -f "${ARCHIVE}" "${ARCHIVE}.sha256"
(
  cd "${BUILD_DIR}"
  find wmcp-agentsnr -print | LC_ALL=C sort | zip -X -q "${ARCHIVE}" -@
)

(
  cd "${DIST_DIR}"
  shasum -a 256 "$(basename "${ARCHIVE}")" > "$(basename "${ARCHIVE}").sha256"
)
echo "Built ${ARCHIVE}"
cat "${ARCHIVE}.sha256"
