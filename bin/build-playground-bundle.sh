#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLAYGROUND_SOURCE="${REPO_DIR}/demo/playground"
PLUGIN_SOURCE="${REPO_DIR}/plugin/wmcp-agentops"
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

VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${PLUGIN_SOURCE}/wmcp-agentops.php" | head -n 1)"
if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9.-]+)?$ ]]; then
  echo "Unable to resolve a valid plugin version." >&2
  exit 1
fi

node -e 'JSON.parse(require("fs").readFileSync(process.argv[1], "utf8"))' "${PLAYGROUND_SOURCE}/blueprint.json"

"${SCRIPT_DIR}/build-plugin.sh"

PLUGIN_ARCHIVE="${DIST_DIR}/wmcp-agentops-${VERSION}.zip"
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

# The plugin builder intentionally packages the exact source tree, but copied
# repository files can inherit build-time mtimes. Normalize a bundle-local copy
# so the outer Blueprint archive is reproducible without changing that builder.
mkdir -p "${BUILD_DIR}/plugin"
unzip -q "${PLUGIN_ARCHIVE}" -d "${BUILD_DIR}/plugin"
if [[ ! -f "${BUILD_DIR}/plugin/wmcp-agentops/wmcp-agentops.php" ]]; then
  echo "Plugin archive does not contain the expected wmcp-agentops root." >&2
  exit 1
fi

find "${BUILD_DIR}/plugin/wmcp-agentops" -exec touch -t 198001010000 {} +
(
  cd "${BUILD_DIR}/plugin"
  find wmcp-agentops -type f -print | LC_ALL=C sort | zip -X -q "${BUILD_DIR}/wmcp-agentops.zip" -@
)

(
  cd "${BUILD_DIR}"
  shasum -a 256 wmcp-agentops.zip > wmcp-agentops.zip.sha256
)

# ZIP stores source mtimes. Normalizing every member makes repeated builds from
# the same source tree byte-for-byte reproducible.
TZ=UTC touch -t 198001010000 \
  "${BUILD_DIR}/blueprint.json" \
  "${BUILD_DIR}/README.txt" \
  "${BUILD_DIR}/seed-demo.php" \
  "${BUILD_DIR}/wmcp-agentops.zip" \
  "${BUILD_DIR}/wmcp-agentops.zip.sha256"

ARCHIVE="${DIST_DIR}/wmcp-agentops-playground-${VERSION}.zip"
rm -f "${ARCHIVE}" "${ARCHIVE}.sha256"
(
  cd "${BUILD_DIR}"
  zip -X -q "${ARCHIVE}" \
    blueprint.json \
    README.txt \
    seed-demo.php \
    wmcp-agentops.zip \
    wmcp-agentops.zip.sha256
)

EXPECTED_LAYOUT="$(printf '%s\n' blueprint.json README.txt seed-demo.php wmcp-agentops.zip wmcp-agentops.zip.sha256)"
ACTUAL_LAYOUT="$(unzip -Z1 "${ARCHIVE}")"
if [[ "${ACTUAL_LAYOUT}" != "${EXPECTED_LAYOUT}" ]]; then
  echo "Unexpected Playground bundle layout:" >&2
  printf '%s\n' "${ACTUAL_LAYOUT}" >&2
  exit 1
fi

unzip -tq "${ARCHIVE}" >/dev/null
unzip -p "${ARCHIVE}" blueprint.json | node -e 'let source = ""; process.stdin.on("data", (chunk) => source += chunk); process.stdin.on("end", () => JSON.parse(source));'

(
  cd "${DIST_DIR}"
  shasum -a 256 "$(basename "${ARCHIVE}")" > "$(basename "${ARCHIVE}").sha256"
)

echo "Built ${ARCHIVE}"
cat "${ARCHIVE}.sha256"
