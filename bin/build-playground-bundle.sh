#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLAYGROUND_SOURCE="${REPO_DIR}/demo/playground"
PLUGIN_SOURCE="${REPO_DIR}/plugin/wmcp-agentsnr"
DIST_DIR="${REPO_DIR}/dist"
BUILD_DIR="$(mktemp -d "${TMPDIR:-/tmp}/wmcp-playground.XXXXXX")"

cleanup() {
  rm -rf "${BUILD_DIR}"
}
trap cleanup EXIT

for required_command in node rsync shasum unzip zip; do
  if ! command -v "${required_command}" >/dev/null 2>&1; then
    echo "Required command not found: ${required_command}" >&2
    exit 1
  fi
done

VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${PLUGIN_SOURCE}/wmcp-agentsnr.php" | head -n 1)"
if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9.-]+)?$ ]]; then
  echo "Unable to resolve a valid plugin version." >&2
  exit 1
fi

node -e 'JSON.parse(require("fs").readFileSync(process.argv[1], "utf8"))' "${PLAYGROUND_SOURCE}/blueprint.json"

"${SCRIPT_DIR}/build-plugin.sh"

PLUGIN_ARCHIVE="${DIST_DIR}/wmcp-agentsnr-${VERSION}.zip"
if [[ ! -f "${PLUGIN_ARCHIVE}" ]]; then
  echo "Plugin build did not produce ${PLUGIN_ARCHIVE}." >&2
  exit 1
fi

# Keep the standalone artifact sidecar portable even though the underlying
# plugin builder reports an absolute path.
(
  cd "${DIST_DIR}"
  shasum -a 256 "$(basename "${PLUGIN_ARCHIVE}")" > "$(basename "${PLUGIN_ARCHIVE}").sha256"
)

mkdir -p "${DIST_DIR}"
cp "${PLAYGROUND_SOURCE}/blueprint.json" "${BUILD_DIR}/blueprint.json"
cp "${PLAYGROUND_SOURCE}/BUNDLE-README.txt" "${BUILD_DIR}/README.txt"
cp "${PLAYGROUND_SOURCE}/seed-demo.php" "${BUILD_DIR}/seed-demo.php"

# Preserve exact-byte provenance: the Playground bundle contains the same
# standalone plugin ZIP that release consumers download.
if ! unzip -Z1 "${PLUGIN_ARCHIVE}" | grep -Fxq 'wmcp-agentsnr/wmcp-agentsnr.php'; then
  echo "Plugin archive does not contain the expected wmcp-agentsnr root." >&2
  exit 1
fi
cp "${PLUGIN_ARCHIVE}" "${BUILD_DIR}/wmcp-agentsnr.zip"

(
  cd "${BUILD_DIR}"
  shasum -a 256 wmcp-agentsnr.zip > wmcp-agentsnr.zip.sha256
)

# ZIP stores source mtimes. Normalizing every member makes repeated builds from
# the same source tree byte-for-byte reproducible.
TZ=UTC touch -t 198001010000 \
  "${BUILD_DIR}/blueprint.json" \
  "${BUILD_DIR}/README.txt" \
  "${BUILD_DIR}/seed-demo.php" \
  "${BUILD_DIR}/wmcp-agentsnr.zip" \
  "${BUILD_DIR}/wmcp-agentsnr.zip.sha256"

ARCHIVE="${DIST_DIR}/wmcp-agentsnr-playground-${VERSION}.zip"
rm -f "${ARCHIVE}" "${ARCHIVE}.sha256"
(
  cd "${BUILD_DIR}"
  zip -X -q "${ARCHIVE}" \
    blueprint.json \
    README.txt \
    seed-demo.php \
    wmcp-agentsnr.zip \
    wmcp-agentsnr.zip.sha256
)

EXPECTED_LAYOUT="$(printf '%s\n' blueprint.json README.txt seed-demo.php wmcp-agentsnr.zip wmcp-agentsnr.zip.sha256)"
ACTUAL_LAYOUT="$(unzip -Z1 "${ARCHIVE}")"
if [[ "${ACTUAL_LAYOUT}" != "${EXPECTED_LAYOUT}" ]]; then
  echo "Unexpected Playground bundle layout:" >&2
  printf '%s\n' "${ACTUAL_LAYOUT}" >&2
  exit 1
fi

unzip -tq "${ARCHIVE}" >/dev/null
unzip -p "${ARCHIVE}" blueprint.json | node -e 'let source = ""; process.stdin.on("data", (chunk) => source += chunk); process.stdin.on("end", () => JSON.parse(source));'
BUNDLED_PLUGIN_SHA="$(unzip -p "${ARCHIVE}" wmcp-agentsnr.zip | shasum -a 256 | awk '{print $1}')"
STANDALONE_PLUGIN_SHA="$(shasum -a 256 "${PLUGIN_ARCHIVE}" | awk '{print $1}')"
if [[ "${BUNDLED_PLUGIN_SHA}" != "${STANDALONE_PLUGIN_SHA}" ]]; then
  echo "Playground bundle plugin bytes do not match the standalone plugin artifact." >&2
  exit 1
fi

(
  cd "${DIST_DIR}"
  shasum -a 256 "$(basename "${ARCHIVE}")" > "$(basename "${ARCHIVE}").sha256"
)

echo "Built ${ARCHIVE}"
cat "${ARCHIVE}.sha256"
