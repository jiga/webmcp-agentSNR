#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
BASE_URL="${WMCP_BASE_URL:-http://localhost:${WMCP_HTTP_PORT:-18080}}"
BASE_URL="${BASE_URL%/}"
SMOKE_TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/wmcp-smoke.XXXXXX")"
COOKIE_JAR="${SMOKE_TEMP_DIR}/cookies.txt"

cleanup() {
  rm -rf "${SMOKE_TEMP_DIR}"
}
trap cleanup EXIT

for required_command in curl jq openssl; do
  if ! command -v "${required_command}" >/dev/null 2>&1; then
    echo "Required command not found: ${required_command}" >&2
    exit 1
  fi
done

request_id() {
  printf 'req_%s' "$(openssl rand -hex 16)"
}

fetch_manifest() {
  local surface="$1"
  local output="$2"
  local referer_path="/storefront-demo/"
  if [[ "${surface}" == "agentops" ]]; then
    referer_path="/agentops-demo/"
  fi
  curl --fail-with-body --silent --show-error \
    --cookie "${COOKIE_JAR}" \
    --cookie-jar "${COOKIE_JAR}" \
    --header "Origin: ${BASE_URL}" \
    --header 'Content-Type: application/json' \
    --data-binary "{\"surface\":\"${surface}\"}" \
    "${BASE_URL}/wp-json/wmcp-agentops/v1/session" >/dev/null
  curl --fail --silent --show-error \
    --cookie "${COOKIE_JAR}" \
    --cookie-jar "${COOKIE_JAR}" \
    --header 'Accept: application/json' \
    --header "Referer: ${BASE_URL}${referer_path}" \
    "${BASE_URL}/wp-json/wmcp-agentops/v1/manifest?surface=${surface}" > "${output}"
  jq -e '.schema_version == "1.0" and (.manifest_revision | startswith("rev_"))' "${output}" >/dev/null
}

invoke_tool() {
  local manifest="$1"
  local tool="$2"
  local input_json="$3"
  local output="$4"
  local payload

  payload="$(jq -cn \
    --arg schema_version "$(jq -r '.schema_version' "${manifest}")" \
    --arg workflow_id "$(jq -r '.workflow_id' "${manifest}")" \
    --arg manifest_revision "$(jq -r '.manifest_revision' "${manifest}")" \
    --arg request_id "$(request_id)" \
    --argjson input "${input_json}" \
    '{schema_version:$schema_version,workflow_id:$workflow_id,manifest_revision:$manifest_revision,request_id:$request_id,input:$input}')"

  curl --fail-with-body --silent --show-error \
    --cookie "${COOKIE_JAR}" \
    --cookie-jar "${COOKIE_JAR}" \
    --header "Origin: ${BASE_URL}" \
    --header 'Content-Type: application/json' \
    --header "X-WMCP-CSRF: $(jq -r '.session.csrf_token' "${manifest}")" \
    --data-binary "${payload}" \
    "${BASE_URL}/wp-json/wmcp-agentops/v1/tools/${tool}" > "${output}"
  jq -e '.ok == true' "${output}" >/dev/null
}

for path in / /storefront-demo/ /agentops-demo/ /webmcp-health/ /shop/ /cart/ /checkout/; do
  curl --fail --silent --show-error "${BASE_URL}${path}" >/dev/null
done

curl --fail --silent --show-error "${BASE_URL}/wp-json/wmcp-agentops/v1/health" \
  | jq -e '.ok == true and .diagnostics.checks.database.status == "passed" and .diagnostics.checks.woocommerce.status == "passed"' >/dev/null

NO_SESSION_STATUS="$(curl --silent --show-error \
  --output "${SMOKE_TEMP_DIR}/session-required.json" --write-out '%{http_code}' \
  --header "Referer: ${BASE_URL}/storefront-demo/" \
  "${BASE_URL}/wp-json/wmcp-agentops/v1/manifest?surface=storefront")"
test "${NO_SESSION_STATUS}" = "401"
jq -e '.error.code == "session_required"' "${SMOKE_TEMP_DIR}/session-required.json" >/dev/null

CROSS_ORIGIN_STATUS="$(curl --silent --show-error \
  --output "${SMOKE_TEMP_DIR}/origin-denied.json" --write-out '%{http_code}' \
  --header 'Origin: https://attacker.invalid' \
  --header 'Content-Type: application/json' \
  --data-binary '{"surface":"storefront"}' \
  "${BASE_URL}/wp-json/wmcp-agentops/v1/session")"
test "${CROSS_ORIGIN_STATUS}" = "403"
jq -e '.error.code == "origin_denied"' "${SMOKE_TEMP_DIR}/origin-denied.json" >/dev/null

FIXED_COOKIE="$(printf 'a%.0s' {1..64})"
curl --fail-with-body --silent --show-error \
  --dump-header "${SMOKE_TEMP_DIR}/fixation-headers.txt" \
  --output "${SMOKE_TEMP_DIR}/fixation.json" \
  --header "Origin: ${BASE_URL}" \
  --header 'Content-Type: application/json' \
  --header "Cookie: wmcp_demo_session=${FIXED_COOKIE}" \
  --data-binary '{"surface":"storefront"}' \
  "${BASE_URL}/wp-json/wmcp-agentops/v1/session"
jq -e '.ok == true' "${SMOKE_TEMP_DIR}/fixation.json" >/dev/null
! grep -q "wmcp_demo_session=${FIXED_COOKIE}" "${SMOKE_TEMP_DIR}/fixation-headers.txt"

STOREFRONT_MANIFEST="${SMOKE_TEMP_DIR}/storefront.json"
AGENTOPS_MANIFEST="${SMOKE_TEMP_DIR}/agentops.json"
fetch_manifest storefront "${STOREFRONT_MANIFEST}"
jq -e '([.tools[].name] | length) == 11 and ([.tools[].name] | index("search_products")) != null and .cart.item_count == 0' "${STOREFRONT_MANIFEST}" >/dev/null

BAD_CSRF_PAYLOAD="$(jq -cn \
  --arg schema_version "$(jq -r '.schema_version' "${STOREFRONT_MANIFEST}")" \
  --arg workflow_id "$(jq -r '.workflow_id' "${STOREFRONT_MANIFEST}")" \
  --arg manifest_revision "$(jq -r '.manifest_revision' "${STOREFRONT_MANIFEST}")" \
  --arg request_id "$(request_id)" \
  '{schema_version:$schema_version,workflow_id:$workflow_id,manifest_revision:$manifest_revision,request_id:$request_id,input:{}}')"
BAD_CSRF_STATUS="$(curl --silent --show-error \
  --output "${SMOKE_TEMP_DIR}/csrf-denied.json" --write-out '%{http_code}' \
  --cookie "${COOKIE_JAR}" --cookie-jar "${COOKIE_JAR}" \
  --header "Origin: ${BASE_URL}" --header 'Content-Type: application/json' \
  --header 'X-WMCP-CSRF: invalid-token' \
  --data-binary "${BAD_CSRF_PAYLOAD}" \
  "${BASE_URL}/wp-json/wmcp-agentops/v1/tools/get_storefront_context")"
test "${BAD_CSRF_STATUS}" = "403"
jq -e '.error.code == "csrf_invalid"' "${SMOKE_TEMP_DIR}/csrf-denied.json" >/dev/null

invoke_tool "${STOREFRONT_MANIFEST}" get_storefront_context '{}' "${SMOKE_TEMP_DIR}/context.json"
invoke_tool "${STOREFRONT_MANIFEST}" search_products \
  '{"query":"waterproof backpack","max_price":120,"in_stock_only":true,"limit":8}' \
  "${SMOKE_TEMP_DIR}/search.json"
jq -e '
  (.result.products | length) >= 2
  and all(.result.products[]; (.name | test("TrailCover|UrbanDry|CanyonDay") | not))
  and all(.result.products[]; (.attributes.water_rating | test("IPX[4-9]|[Ww]aterproof")))
' "${SMOKE_TEMP_DIR}/search.json" >/dev/null

PRODUCT_ID="$(jq -r '.result.products[0].id' "${SMOKE_TEMP_DIR}/search.json")"
SECOND_PRODUCT_ID="$(jq -r '.result.products[1].id' "${SMOKE_TEMP_DIR}/search.json")"
invoke_tool "${STOREFRONT_MANIFEST}" compare_products \
  "{\"product_ids\":[${PRODUCT_ID},${SECOND_PRODUCT_ID}],\"criteria\":[\"price\",\"capacity\",\"water_rating\",\"return_days\"]}" \
  "${SMOKE_TEMP_DIR}/compare.json"
invoke_tool "${STOREFRONT_MANIFEST}" get_store_policy \
  "{\"policy_type\":\"returns\",\"product_id\":${PRODUCT_ID}}" \
  "${SMOKE_TEMP_DIR}/policy.json"
jq -e '.result.policies[0].facts.return_days >= 30' "${SMOKE_TEMP_DIR}/policy.json" >/dev/null

invoke_tool "${STOREFRONT_MANIFEST}" get_cart '{}' "${SMOKE_TEMP_DIR}/initial-cart.json"
invoke_tool "${STOREFRONT_MANIFEST}" add_to_cart \
  "{\"product_id\":${PRODUCT_ID},\"quantity\":1,\"expected_cart_revision\":\"$(jq -r '.result.cart_revision' "${SMOKE_TEMP_DIR}/initial-cart.json")\"}" \
  "${SMOKE_TEMP_DIR}/added.json"
invoke_tool "${STOREFRONT_MANIFEST}" get_cart '{}' "${SMOKE_TEMP_DIR}/cart.json"
jq -e '.result.item_count == 1' "${SMOKE_TEMP_DIR}/cart.json" >/dev/null
fetch_manifest storefront "${SMOKE_TEMP_DIR}/storefront-cart.json"
jq -e '.cart.item_count == 1' "${SMOKE_TEMP_DIR}/storefront-cart.json" >/dev/null

STALE_CART_PAYLOAD="$(jq -cn \
  --arg schema_version "$(jq -r '.schema_version' "${STOREFRONT_MANIFEST}")" \
  --arg workflow_id "$(jq -r '.workflow_id' "${STOREFRONT_MANIFEST}")" \
  --arg manifest_revision "$(jq -r '.manifest_revision' "${STOREFRONT_MANIFEST}")" \
  --arg request_id "$(request_id)" \
  '{schema_version:$schema_version,workflow_id:$workflow_id,manifest_revision:$manifest_revision,request_id:$request_id,input:{expected_cart_revision:"cartrev_000000000000000000000000"}}')"
STALE_CART_STATUS="$(curl --silent --show-error --output "${SMOKE_TEMP_DIR}/stale-cart.json" --write-out '%{http_code}' \
  --cookie "${COOKIE_JAR}" --cookie-jar "${COOKIE_JAR}" \
  --header "Origin: ${BASE_URL}" --header 'Content-Type: application/json' \
  --header "X-WMCP-CSRF: $(jq -r '.session.csrf_token' "${STOREFRONT_MANIFEST}")" \
  --data-binary "${STALE_CART_PAYLOAD}" \
  "${BASE_URL}/wp-json/wmcp-agentops/v1/tools/checkout_handoff")"
test "${STALE_CART_STATUS}" = "409"
jq -e '.error.code == "stale_cart_revision"' "${SMOKE_TEMP_DIR}/stale-cart.json" >/dev/null
invoke_tool "${STOREFRONT_MANIFEST}" report_capability_gap \
  "{\"requested_capability\":\"back_in_stock_notification\",\"user_goal\":\"Notify the shopper when the blue option is available.\",\"related_product_id\":${PRODUCT_ID},\"context\":{\"color\":\"blue\"}}" \
  "${SMOKE_TEMP_DIR}/gap.json"
jq -e '.result.recorded == true and .result.fulfilled == false' "${SMOKE_TEMP_DIR}/gap.json" >/dev/null
invoke_tool "${STOREFRONT_MANIFEST}" checkout_handoff \
  "{\"expected_cart_revision\":\"$(jq -r '.result.cart_revision' "${SMOKE_TEMP_DIR}/cart.json")\"}" \
  "${SMOKE_TEMP_DIR}/handoff.json"
jq -e '.result.checkout_url | contains("/checkout/")' "${SMOKE_TEMP_DIR}/handoff.json" >/dev/null

REPLAY_REQUEST_ID="$(request_id)"
REPLAY_INPUT="{\"product_ids\":[${PRODUCT_ID},${SECOND_PRODUCT_ID}]}"
REPLAY_PAYLOAD="$(jq -cn \
  --arg schema_version "$(jq -r '.schema_version' "${STOREFRONT_MANIFEST}")" \
  --arg workflow_id "$(jq -r '.workflow_id' "${STOREFRONT_MANIFEST}")" \
  --arg manifest_revision "$(jq -r '.manifest_revision' "${STOREFRONT_MANIFEST}")" \
  --arg request_id "${REPLAY_REQUEST_ID}" \
  --argjson input "${REPLAY_INPUT}" \
  '{schema_version:$schema_version,workflow_id:$workflow_id,manifest_revision:$manifest_revision,request_id:$request_id,input:$input}')"
curl --fail-with-body --silent --show-error \
  --cookie "${COOKIE_JAR}" --cookie-jar "${COOKIE_JAR}" \
  --header "Origin: ${BASE_URL}" --header 'Content-Type: application/json' \
  --header "X-WMCP-CSRF: $(jq -r '.session.csrf_token' "${STOREFRONT_MANIFEST}")" \
  --data-binary "${REPLAY_PAYLOAD}" \
  "${BASE_URL}/wp-json/wmcp-agentops/v1/tools/compare_products" \
  | jq -e '.ok == true' >/dev/null

CONFLICT_PAYLOAD="$(jq -cn \
  --arg schema_version "$(jq -r '.schema_version' "${STOREFRONT_MANIFEST}")" \
  --arg workflow_id "$(jq -r '.workflow_id' "${STOREFRONT_MANIFEST}")" \
  --arg manifest_revision "$(jq -r '.manifest_revision' "${STOREFRONT_MANIFEST}")" \
  --arg request_id "${REPLAY_REQUEST_ID}" \
  '{schema_version:$schema_version,workflow_id:$workflow_id,manifest_revision:$manifest_revision,request_id:$request_id,input:{}}')"
CONFLICT_STATUS="$(curl --silent --show-error --output "${SMOKE_TEMP_DIR}/request-conflict.json" --write-out '%{http_code}' \
  --cookie "${COOKIE_JAR}" --cookie-jar "${COOKIE_JAR}" \
  --header "Origin: ${BASE_URL}" --header 'Content-Type: application/json' \
  --header "X-WMCP-CSRF: $(jq -r '.session.csrf_token' "${STOREFRONT_MANIFEST}")" \
  --data-binary "${CONFLICT_PAYLOAD}" \
  "${BASE_URL}/wp-json/wmcp-agentops/v1/tools/get_cart")"
test "${CONFLICT_STATUS}" = "409"
jq -e '.error.code == "request_id_conflict"' "${SMOKE_TEMP_DIR}/request-conflict.json" >/dev/null

fetch_manifest agentops "${AGENTOPS_MANIFEST}"
jq -e '([.tools[].name] | length) == 8 and ([.tools[].name] | index("set_tool_enabled")) != null' "${AGENTOPS_MANIFEST}" >/dev/null
invoke_tool "${AGENTOPS_MANIFEST}" get_agent_analytics_overview '{}' "${SMOKE_TEMP_DIR}/overview.json"
jq -e '.result.workflows.total >= 1 and .result.tool_calls.total >= 1 and .result.capability_gaps.requests >= 1' "${SMOKE_TEMP_DIR}/overview.json" >/dev/null

invoke_tool "${AGENTOPS_MANIFEST}" set_tool_enabled \
  '{"tool_name":"compare_products","enabled":false,"scope":"demo_session","reason":"Automated session-governance smoke test"}' \
  "${SMOKE_TEMP_DIR}/disabled.json"
jq -e '.result.after.enabled == false' "${SMOKE_TEMP_DIR}/disabled.json" >/dev/null

fetch_manifest storefront "${SMOKE_TEMP_DIR}/storefront-disabled.json"
jq -e '([.tools[].name] | index("compare_products")) == null' "${SMOKE_TEMP_DIR}/storefront-disabled.json" >/dev/null

DENIED_PAYLOAD="$(jq -cn \
  --arg schema_version "$(jq -r '.schema_version' "${SMOKE_TEMP_DIR}/storefront-disabled.json")" \
  --arg workflow_id "$(jq -r '.workflow_id' "${SMOKE_TEMP_DIR}/storefront-disabled.json")" \
  --arg manifest_revision "$(jq -r '.manifest_revision' "${SMOKE_TEMP_DIR}/storefront-disabled.json")" \
  --arg request_id "${REPLAY_REQUEST_ID}" \
  --argjson input "${REPLAY_INPUT}" \
  '{schema_version:$schema_version,workflow_id:$workflow_id,manifest_revision:$manifest_revision,request_id:$request_id,input:$input}')"
DENIED_STATUS="$(curl --silent --show-error \
  --output "${SMOKE_TEMP_DIR}/denied.json" \
  --write-out '%{http_code}' \
  --cookie "${COOKIE_JAR}" \
  --cookie-jar "${COOKIE_JAR}" \
  --header "Origin: ${BASE_URL}" \
  --header 'Content-Type: application/json' \
  --header "X-WMCP-CSRF: $(jq -r '.session.csrf_token' "${SMOKE_TEMP_DIR}/storefront-disabled.json")" \
  --data-binary "${DENIED_PAYLOAD}" \
  "${BASE_URL}/wp-json/wmcp-agentops/v1/tools/compare_products")"
test "${DENIED_STATUS}" = "403"
jq -e '.error.code == "tool_disabled"' "${SMOKE_TEMP_DIR}/denied.json" >/dev/null

fetch_manifest agentops "${SMOKE_TEMP_DIR}/agentops-current.json"
invoke_tool "${SMOKE_TEMP_DIR}/agentops-current.json" set_tool_enabled \
  '{"tool_name":"compare_products","enabled":true,"scope":"demo_session","reason":"Restore the smoke-test baseline"}' \
  "${SMOKE_TEMP_DIR}/restored.json"
fetch_manifest storefront "${SMOKE_TEMP_DIR}/storefront-restored.json"
jq -e '([.tools[].name] | index("compare_products")) != null' "${SMOKE_TEMP_DIR}/storefront-restored.json" >/dev/null

fetch_manifest storefront "${SMOKE_TEMP_DIR}/before-reset.json"
curl --fail-with-body --silent --show-error \
  --cookie "${COOKIE_JAR}" \
  --cookie-jar "${COOKIE_JAR}" \
  --header "Origin: ${BASE_URL}" \
  --header "X-WMCP-CSRF: $(jq -r '.session.csrf_token' "${SMOKE_TEMP_DIR}/before-reset.json")" \
  --request POST \
  "${BASE_URL}/wp-json/wmcp-agentops/v1/demo/reset?surface=storefront" \
  --output "${SMOKE_TEMP_DIR}/reset-manifest.json"
jq -e '.ok == true and .manifest.surface == "storefront"' "${SMOKE_TEMP_DIR}/reset-manifest.json" >/dev/null

jq '.manifest' "${SMOKE_TEMP_DIR}/reset-manifest.json" > "${SMOKE_TEMP_DIR}/after-reset.json"
jq -e '.cart.item_count == 0' "${SMOKE_TEMP_DIR}/after-reset.json" >/dev/null
invoke_tool "${SMOKE_TEMP_DIR}/after-reset.json" get_cart '{}' "${SMOKE_TEMP_DIR}/reset-cart.json"
jq -e '.result.item_count == 0' "${SMOKE_TEMP_DIR}/reset-cart.json" >/dev/null

echo "Smoke tests passed: pages, 19-tool catalogs, shopper flow, analytics, governance, server denial, and isolated reset."
