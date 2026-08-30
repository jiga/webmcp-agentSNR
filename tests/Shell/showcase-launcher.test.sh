#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SHOWCASE="${REPO_DIR}/bin/start-showcase.sh"
RUNTIME_ROOT="${REPO_DIR}/.release-test/showcase-runtime"
ARTIFACTS_DIR="${RUNTIME_ROOT}/artifacts"
STATE_FILE="${RUNTIME_ROOT}/active-artifact"
TEST_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/agent-snr-showcase-test.XXXXXX")"
TEST_HASH=""
TEST_CACHE=""
STATE_BACKUP="${TEST_ROOT}/active-artifact.backup"

cleanup() {
  local rejected

  if [[ -n "${TEST_HASH}" && "${TEST_HASH}" =~ ^[a-f0-9]{64}$ \
    && "${TEST_CACHE}" == "${ARTIFACTS_DIR}/${TEST_HASH}" ]]; then
    rm -rf -- "${TEST_CACHE}"
    for rejected in "${TEST_CACHE}.rejected."*; do
      [[ -e "${rejected}" ]] && rm -rf -- "${rejected}"
    done
  fi
  if [[ -f "${STATE_BACKUP}" ]]; then
    mkdir -p "${RUNTIME_ROOT}"
    cp "${STATE_BACKUP}" "${STATE_FILE}"
  else
    rm -f -- "${STATE_FILE}"
  fi
  rm -rf -- "${TEST_ROOT}"
}
trap cleanup EXIT

if [[ -f "${STATE_FILE}" ]]; then
  cp "${STATE_FILE}" "${STATE_BACKUP}"
fi

mkdir -p "${TEST_ROOT}/bin"
printf '%s\n' \
  '#!/usr/bin/env bash' \
  'if [[ -n "${SHOWCASE_DOCKER_TRACE:-}" ]]; then' \
  '  printf "%s|%s|%s|%s|%s\n" "${COMPOSE_PROJECT_NAME:-}" "${WMCP_BIND_HOST:-}" "${WMCP_HTTP_PORT:-}" "${WMCP_PLUGIN_DIR:-}" "$*" >> "${SHOWCASE_DOCKER_TRACE}"' \
  'fi' \
  'exit "${SHOWCASE_DOCKER_EXIT:-0}"' > "${TEST_ROOT}/bin/docker"
chmod 700 "${TEST_ROOT}/bin/docker"

bash -n "${SHOWCASE}"

help_output="$("${SHOWCASE}" help)"
grep -Fq 'reset --confirm-agent-snr-showcase' <<< "${help_output}"
grep -Fq 'Exact release ZIP to run' <<< "${help_output}"
grep -Fq 'start builds the deterministic' <<< "${help_output}"

if WMCP_SHOWCASE_PORT=0 "${SHOWCASE}" status >/dev/null 2>&1; then
  echo "Expected the showcase launcher to reject port 0." >&2
  exit 1
fi

docker_trace="${TEST_ROOT}/docker.trace"
if SHOWCASE_DOCKER_TRACE="${docker_trace}" \
  PATH="${TEST_ROOT}/bin:${PATH}" \
  "${SHOWCASE}" reset --wrong-confirmation >/dev/null 2>&1; then
  echo "Expected the showcase launcher to reject the wrong reset confirmation." >&2
  exit 1
fi
[[ ! -s "${docker_trace}" ]] || {
  echo "Wrong reset confirmation reached Docker." >&2
  exit 1
}

: > "${docker_trace}"
SHOWCASE_DOCKER_TRACE="${docker_trace}" \
  PATH="${TEST_ROOT}/bin:${PATH}" \
  WMCP_SHOWCASE_PORT=18086 \
  "${SHOWCASE}" status >/dev/null
grep -Fq 'agent-snr-showcase|127.0.0.1|18086|' "${docker_trace}"
grep -Fq 'compose --project-name agent-snr-showcase' "${docker_trace}"

missing_zip="${TEST_ROOT}/does-not-exist.zip"
zip_output=""
if zip_output="$(WMCP_SHOWCASE_ZIP="${missing_zip}" "${SHOWCASE}" verify 2>&1)"; then
  echo "Expected the showcase launcher to reject a missing release ZIP." >&2
  exit 1
fi
grep -Fq 'Release ZIP not found' <<< "${zip_output}"

mkdir -p "${TEST_ROOT}/checksum/wmcp-agentops"
printf '%s\n' '<?php // checksum fixture' > "${TEST_ROOT}/checksum/wmcp-agentops/wmcp-agentops.php"
checksum_zip="${TEST_ROOT}/checksum-mismatch.zip"
( cd "${TEST_ROOT}/checksum" && zip -q -r "${checksum_zip}" wmcp-agentops )
printf '%064d  %s\n' 0 "$(basename "${checksum_zip}")" > "${checksum_zip}.sha256"
checksum_output=""
if checksum_output="$(WMCP_SHOWCASE_ZIP="${checksum_zip}" "${SHOWCASE}" verify 2>&1)"; then
  echo "Expected the showcase launcher to reject a checksum mismatch." >&2
  exit 1
fi
grep -Fq 'checksum does not match' <<< "${checksum_output}"

mkdir -p "${TEST_ROOT}/symlink/wmcp-agentops"
printf '%s\n' '<?php // symlink fixture' > "${TEST_ROOT}/symlink/wmcp-agentops/wmcp-agentops.php"
ln -s wmcp-agentops.php "${TEST_ROOT}/symlink/wmcp-agentops/linked.php"
symlink_zip="${TEST_ROOT}/symlink-entry.zip"
( cd "${TEST_ROOT}/symlink" && zip -y -q -r "${symlink_zip}" wmcp-agentops )
( cd "${TEST_ROOT}" && shasum -a 256 "$(basename "${symlink_zip}")" > "$(basename "${symlink_zip}").sha256" )
symlink_output=""
if symlink_output="$(WMCP_SHOWCASE_ZIP="${symlink_zip}" "${SHOWCASE}" verify 2>&1)"; then
  echo "Expected the showcase launcher to reject a symlink archive entry." >&2
  exit 1
fi
grep -Fq 'unsupported entry type' <<< "${symlink_output}"

mkdir -p "${TEST_ROOT}/foreign/not-agent-snr"
printf '%s\n' '<?php // foreign-root fixture' > "${TEST_ROOT}/foreign/not-agent-snr/plugin.php"
foreign_zip="${TEST_ROOT}/foreign-root.zip"
( cd "${TEST_ROOT}/foreign" && zip -q -r "${foreign_zip}" not-agent-snr )
( cd "${TEST_ROOT}" && shasum -a 256 "$(basename "${foreign_zip}")" > "$(basename "${foreign_zip}").sha256" )
foreign_output=""
if foreign_output="$(WMCP_SHOWCASE_ZIP="${foreign_zip}" "${SHOWCASE}" verify 2>&1)"; then
  echo "Expected the showcase launcher to reject a foreign archive root." >&2
  exit 1
fi
grep -Fq 'one safe top-level wmcp-agentops' <<< "${foreign_output}"

mkdir -p "${TEST_ROOT}/cache/wmcp-agentops"
printf '%s\n' "<?php // cache fixture $$" > "${TEST_ROOT}/cache/wmcp-agentops/wmcp-agentops.php"
cache_zip="${TEST_ROOT}/cache-integrity.zip"
( cd "${TEST_ROOT}/cache" && zip -q -r "${cache_zip}" wmcp-agentops )
( cd "${TEST_ROOT}" && shasum -a 256 "$(basename "${cache_zip}")" > "$(basename "${cache_zip}").sha256" )
TEST_HASH="$(shasum -a 256 "${cache_zip}" | awk '{ print $1 }')"
TEST_CACHE="${ARTIFACTS_DIR}/${TEST_HASH}"

SHOWCASE_DOCKER_EXIT=1 \
  PATH="${TEST_ROOT}/bin:${PATH}" \
  WMCP_SHOWCASE_ZIP="${cache_zip}" \
  "${SHOWCASE}" verify >/dev/null 2>&1 || true
[[ -f "${TEST_CACHE}/wmcp-agentops/wmcp-agentops.php" ]] || {
  echo "Expected verify to prepare the checked release cache before service checks." >&2
  exit 1
}
printf '%s\n' '// tampered' >> "${TEST_CACHE}/wmcp-agentops/wmcp-agentops.php"

: > "${docker_trace}"
cache_output=""
if cache_output="$(SHOWCASE_DOCKER_TRACE="${docker_trace}" \
  PATH="${TEST_ROOT}/bin:${PATH}" \
  WMCP_SHOWCASE_ZIP="${cache_zip}" \
  "${SHOWCASE}" verify 2>&1)"; then
  echo "Expected verify to reject a modified extracted cache." >&2
  exit 1
fi
grep -Fq 'differs from the verified ZIP' <<< "${cache_output}"
[[ ! -s "${docker_trace}" ]] || {
  echo "Modified artifact cache reached Docker." >&2
  exit 1
}

repair_output="$(SHOWCASE_DOCKER_EXIT=1 \
  PATH="${TEST_ROOT}/bin:${PATH}" \
  WMCP_SHOWCASE_ZIP="${cache_zip}" \
  "${SHOWCASE}" start 2>&1 || true)"
grep -Fq 'Replaced a modified artifact cache' <<< "${repair_output}"
cmp "${TEST_ROOT}/cache/wmcp-agentops/wmcp-agentops.php" \
  "${TEST_CACHE}/wmcp-agentops/wmcp-agentops.php"

echo "Showcase launcher guards passed."
