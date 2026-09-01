# nekuda WebMCP Workbench validation

Status: **PENDING OWNER RUN**

Release baseline: **Workbench 1.2.1**

Scope: the frozen, top-level HTTPS storefront and Agent SNR pages

This is a fill-in evidence sheet, not proof that an external test has run. Complete it against the exact public release after deployment, then link only sanitized evidence from the submission. The [Chrome Web Store listing](https://chromewebstore.google.com/detail/nekuda-webmcp-workbench/amochnnbmnkjjlblolhpddkokhnalkjp) describes Tools, Evals, Audit, Logs, Saved calls, approvals, and User Mode; this checklist exercises each relevant surface.

## Release identity

| Field | Recorded value |
|---|---|
| Test date/time and timezone | `[YYYY-MM-DD HH:MM TZ]` |
| Tester | `[OWNER NAME]` |
| Public repository commit | `[FULL COMMIT SHA]` |
| Release tag | `[TAG]` |
| Plugin ZIP SHA-256 | `[SHA-256]` |
| Hosted plugin version | `[VERSION]` |
| Storefront URL | `[PUBLIC_STOREFRONT_URL]` |
| Agent SNR URL | `[PUBLIC_AGENT_SNR_URL]` |
| Chrome version | `[VERSION]` |
| WebMCP testing flag | `[ENABLED / ORIGIN-TRIAL DETAILS]` |
| Workbench version | `[1.2.1 OR REVIEWED NEWER VERSION]` |
| User Mode model/provider | `[EXACT MODEL AND PROVIDER]` |

If the extension store has auto-updated beyond 1.2.1, record the installed version, review its current permissions and release notes, and rerun this entire sheet. Do not silently present a newer run as 1.2.1 evidence.

## Safe setup

- [ ] Use a dedicated Chrome profile and only the frozen demo URLs above.
- [ ] Confirm the deployed commit, tag, plugin version, and checksum match the release identity table.
- [ ] Use fictional demo data. Do not enter real customer, address, order, payment, cookie, or session data.
- [ ] Keep provider keys in the Workbench/browser configuration only. Never paste a key into a prompt, page, screenshot, terminal transcript, issue, or repository file.
- [ ] Leave approvals enabled. Review each proposed write before approving it; do not weaken browser or extension protections for the recording.
- [ ] Store raw exported logs privately. Before sharing any excerpt, remove keys, authorization material, cookies, CSRF values, session identifiers, customer data, and unrelated browsing history.

The store listing says bring-your-own-provider keys are stored locally and encrypted. Treat that as a product claim, not permission to publish keys or raw logs.

## Discovery and schema

Reload each URL before recording its result. Tools must be available on page load without a preliminary click.

| Check | Expected | Actual | Result / sanitized evidence |
|---|---|---|---|
| Storefront API detected | `document.modelContext` available | `[VALUE]` | `[PASS/FAIL — LINK OR NOTE]` |
| Storefront tools | 12 canonical tools | `[COUNT]` | `[PASS/FAIL — LINK OR NOTE]` |
| Agent SNR API detected | `document.modelContext` available | `[VALUE]` | `[PASS/FAIL — LINK OR NOTE]` |
| Agent SNR tools | 8 canonical tools | `[COUNT]` | `[PASS/FAIL — LINK OR NOTE]` |
| Schemas | Strict objects; documented fields; no legacy public gap tools | `[OBSERVATION]` | `[PASS/FAIL — LINK OR NOTE]` |
| Trust metadata | Read-only annotations match behavior; untrusted content is marked | `[OBSERVATION]` | `[PASS/FAIL — LINK OR NOTE]` |
| Sensitive Actions | None registered | `[COUNT]` | `[PASS/FAIL — LINK OR NOTE]` |

Record every unexpected, duplicate, legacy, or late-registered tool as a failure until explained and fixed.

## Manual calls

Use IDs and revisions returned by the frozen site; do not copy stale values from this repository or another browser profile.

| Case | Procedure | Required result | Actual / evidence |
|---|---|---|---|
| Guide | Call `get_agent_guide`. | Co-browsing scope, two outcomes, privacy, optional feedback, reversible effects, and human checkout boundary are explicit. | `[RESULT]` |
| Exact constraint | Search for an in-stock waterproof backpack under $100 with IPX5. | Zero-result response explains the constraint and records a bounded site-observed opportunity. | `[RESULT]` |
| Recover | Relax only the rating to IPX4, inspect results, compare two products, and read the returns policy. | RainTrail and HarborLite are grounded in returned facts; no missing fact is invented. | `[RESULT]` |
| Reversible write | Add HarborLite, change quantity, then restore the intended cart. | Approval behavior is recorded; visible cart and returned revision stay synchronized. | `[RESULT]` |
| Stale state | Save a cart write, change the cart in another tab, then replay the stale write. | The stale revision fails with a recovery instruction; `get_cart` plus one fresh retry succeeds. | `[RESULT]` |
| Invalid input | Add one unknown field and try one invalid enum or identifier. | Strict validation rejects each call with a useful error and no state change. | `[RESULT]` |
| Handoff | Call `prepare_checkout_handoff`. | A normal checkout URL is revealed; no navigation, customer submission, order, acceptance, or payment occurs. | `[RESULT]` |
| Feedback | Submit one guide-triggered structured report using same-workflow evidence. | Receipt is `agent_reported`; caller-supplied free text or metrics are not accepted. | `[RESULT]` |
| Operator read | Run overview, workflow query/explanation, funnel, health, signals, and diagnostics on Agent SNR. | Evidence stays in the private demo session and preserves Site observed / Agent reported / Site verified provenance. | `[RESULT]` |
| Session control | Disable comparison, refresh tools, verify it is unavailable/denied, then restore it. | Restriction is immediate and session-only; a separate browser profile is unaffected. | `[RESULT]` |

Before and after the handoff call, record the WooCommerce order count. It must remain unchanged until a person completes the ordinary checkout UI.

## Audit, evals, replay, and User Mode

| Gate | Required result | Recorded result / evidence |
|---|---|---|
| Audit | Record the 0–100 score and every finding. Remediate or explicitly accept each item with a reason. | `[SCORE; FINDINGS; DISPOSITION]` |
| Saved calls | Save representative Answer and Action calls; refresh/reload the manifest; replay them without schema drift. | `[PASS/FAIL — LINK OR NOTE]` |
| Repeated evals | Run the shopper and operator prompts three times on one recorded model/version. Every authored case and required step passes in all three runs (100%); unsafe state-changing calls are zero. | `[MODEL; RUNS; RATE; FAILURES]` |
| User Mode | Complete guide → constraint → recovery → comparison/policy → cart → handoff. | The last required commerce step is `prepare_checkout_handoff`; optional feedback may follow, order count is unchanged, and final checkout remains human-owned. | `[PASS/FAIL — LINK OR NOTE]` |
| Logs and approvals | Review the call/approval trace for expected ordering, useful errors, and no unexplained call. | `[PASS/FAIL — PRIVATE LOG LOCATION]` |

An Audit score alone is not a release gate. Tool discovery, actual calls, recovery behavior, safety, repeated evals, and User Mode must all pass.

## Finding disposition

| ID | Workbench area | Finding | Severity | Resolution or explicit acceptance | Retest |
|---|---|---|---|---|---|
| `WB-001` | `[Tools/Evals/Audit/Logs/User Mode]` | `[FINDING]` | `[BLOCKER/HIGH/MEDIUM/LOW]` | `[CHANGE + COMMIT, OR REASON]` | `[PASS/FAIL]` |

Add rows until every finding is accounted for. A blocker or unexplained high-severity finding prevents release.

## Owner sign-off

- [ ] Every required row above is complete; no placeholder remains.
- [ ] All failures were fixed in a new release candidate and the full sheet was rerun.
- [ ] Raw logs and provider credentials remain private.
- [ ] Any linked screenshot or excerpt is sanitized and contains only fictional demo data.
- [ ] The tested URLs still serve the exact recorded release.

Owner: `[NAME]`

Date: `[YYYY-MM-DD]`

Decision: `[PASS / BLOCKED]`

Private evidence location: `[ACCESS-CONTROLLED LOCATION]`
