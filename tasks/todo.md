# Submission-readiness plan

Source of truth: the supplied “WordPress WebMCP AgentOps — Full Hackathon Engineering & Submission Handoff,” verified through August 29, 2026.

## Phase 0 — Repository and contracts

- [x] Initialize Git without rewriting later submission history.
- [x] Add GPL-2.0-or-later license and hackathon disclosure.
- [x] Define shared tool names, event names, risk classes, manifest/REST envelopes, DB version, and attribution rule version.
- [x] Add dependency manifests, formatting/lint configuration, CI skeleton, and repository hygiene.
- [x] Separate local build-complete requirements from owner/external submission actions.

## Phase 1 — One complete WebMCP slice

- [x] Bootstrap/activate/deactivate/uninstall the plugin safely.
- [x] Register WordPress Abilities on the canonical hooks.
- [x] Create versioned, idempotent analytics schema migrations.
- [x] Bootstrap an isolated demo session with a short-lived CSRF token.
- [x] Serve a dynamic surface-scoped manifest.
- [x] Register top-level imperative tools with `document.modelContext.registerTool()`.
- [x] Execute through same-origin REST with permission, policy, validation, rate-limit, and event-integrity checks.
- [x] Reflect successful calls in visible UI state.

## Phase 2 — Shopper workflow

- [x] Implement context, product search/get/compare, policy, cart read/write, checkout handoff, and capability-gap tools.
- [x] Seed deterministic fictional products, policy content, and original artwork.
- [x] Build judge landing, storefront workflow rail, result/comparison/policy/cart panels, and accessibility behavior.
- [x] Keep the human storefront usable without WebMCP.

## Phase 3 — Commerce and attribution

- [x] Initialize and scope WooCommerce sessions safely.
- [x] Add the explicit demo-only no-charge gateway.
- [x] Link created/paid/cancelled/refunded orders through WooCommerce CRUD and HPOS-safe hooks.
- [x] Implement direct/assisted/influenced attribution and gross/refund/net calculations.
- [x] Preserve evidence and prevent revenue double counting.

## Phase 4 — AgentOps and governance

- [x] Implement overview, funnel, workflow explorer/explanation, tool health, and capability-gap queries.
- [x] Build public current-session AgentOps and authenticated admin surfaces.
- [x] Implement session-scoped `set_tool_enabled`, persistent admin policy, and global kill switch.
- [x] Refresh registrations after manifest invalidation and enforce changes server-side immediately.

## Phase 5 — Verification and hardening

- [x] Unit-test deterministic core services.
- [x] Integration-test WordPress, REST, WooCommerce, HPOS on/off, isolation, and reset behavior.
- [x] Browser-test progressive enhancement, top-level/iframe behavior, cancellation, UI synchronization, and governance refresh.
- [x] Security-test schemas, CSRF/origin/replay, cross-session access, PII redaction, cache safety, and kill switch.
- [x] Run formatters, static analysis, tests, builds, and install-from-ZIP smoke checks.
- [x] Resolve final-review failure paths, repeat the full verification matrix, and obtain approval.

## Phase 6 — Reproduction and submission package

- [x] Provide one-command Docker reproduction and idempotent bootstrap/reset scripts.
- [x] Build and validate the installable plugin ZIP.
- [x] Build and validate the WordPress Playground Blueprint bundle.
- [x] Add CI and tagged-release automation.
- [x] Finish README, SECURITY, CONTRIBUTING, TESTING, notices, changelog, Devpost copy, judge instructions, screenshots plan, video script, and final checklist.
- [x] Mark all external owner actions explicitly: formal name clearance, public Git host/slug, live HTTPS deployment, real-client validation, screenshots, public YouTube upload, and Devpost submission/freeze.

## Review

Implementation and the definitive local verification matrix are complete. Independent staff review approved the code with no remaining P0/P1 blocker; the exact final artifact passed every configured matrix target.

- PHP: 58 tests / 521 assertions pass on PHP 8.1 and 8.4; Coding Standards has zero errors.
- Browser code: 63 JavaScript tests, 13 Chromium scenarios, lint, and dependency audit pass.
- WordPress/Woo: the exact ZIP passes WordPress 6.9, 7.0.4, and 7.1 with WooCommerce 10.9.4/11.0.1, legacy storage and HPOS; real paid/refund/cancel attribution checks pass in both storage modes.
- Security/REST: 19-tool manifests, session fixation, Origin/CSRF, stale cart, replay conflict, policy enforcement, cross-session isolation, cache neutrality, and reset pass.
- Packaging: Plugin Check has zero errors and zero trademark findings; the remaining direct-query/template warnings and formal Agent SNR clearance action are documented.

Remaining work is exclusively owner/external: complete formal Agent SNR trademark/domain clearance, confirm author identity, create/push the public remote, deploy top-level HTTPS, validate real clients, rerun against WooCommerce 11.1 if released, capture media, replace URLs, submit, and freeze.

## Agent Experience Monitoring iteration

- [x] Compare established session replay, digital experience monitoring, and agent observability products.
- [x] Define the primary operator, category position, core monitoring objects, and explicit privacy boundary.
- [x] Reframe the AgentOps surface around Monitor, Agent Sessions, Journey, Tools, Signals, and Controls.
- [x] Add an agent journey model strip and a truthful loaded-snapshot Signals view using existing redacted evidence.
- [x] Rename the public surface away from the existing AgentOps.ai market association without changing internal compatibility identifiers.
- [x] Update product positioning and judge-facing documentation.
- [x] Run JavaScript/PHP/browser checks and complete independent review; commit the iteration.

## Agent SNR brand finalization

- [x] Select Agent SNR as the final public submission brand.
- [x] Replace public product-name references across plugin UI, package metadata, documentation, and submission copy.
- [x] Preserve internal `wmcp-agentops`, `WPWebMCP\AgentOps`, REST, option, database, route, shortcode, and tool compatibility identifiers.
- [x] Rebuild deterministic artifacts and refresh exact Plugin Check/checksum evidence.
- [x] Run the full verification matrix and review the final diff.
- [x] Commit the brand finalization.

### Agent SNR finalization review

- Public identity is consistently Agent SNR, with the descriptor “Agent outcome monitoring for WordPress” and SNR retaining its established signal-to-noise ratio meaning.
- Judge navigation exposes unique **Agent SNR — Overview** and **Agent SNR — Monitor** labels; the monitor retains the exact **Agent SNR** product H1.
- Final deterministic artifacts: plugin `e25c4dee2fa1b5d6456002798da3fac5c27a8a8d99f2e20af4a8d2a6164bdee0`; Playground `d666f777259aaa2738171aab0db093ddbf019752ff838c84a8cf3b86510db2ea`.
- Exact-ZIP WordPress 6.9/7.0.4/7.1 matrix, legacy/HPOS Woo lifecycle, smoke/security, 13 Chromium scenarios, Playground execution, and Plugin Check all pass. Plugin Check reports 0 errors, 54 documented warnings, and 0 trademark findings.

## Storefront cart-badge synchronization

- [x] Reproduce the stale top-navigation badge after a WebMCP cart mutation.
- [x] Trace the authoritative cart result through every visible cart consumer.
- [x] Add regression coverage for the badge text and accessible item-count label.
- [x] Implement the smallest shared-state synchronization fix.
- [x] Run focused/full browser and static verification and complete independent review.
- [x] Commit the cart-badge fix.

### Cart-badge synchronization review

- Root cause: cart tool results updated only the executing document, while cache-neutral HTML intentionally carried no per-session cart value.
- Fix: the private/no-store storefront manifest now carries only `cart.item_count`; successful cart mutations trigger the existing same-surface refresh broadcast, and each tab refetches its own authoritative session snapshot.
- Privacy/performance: no cart payload or session identifier is broadcast, observers execute no synthetic tools, successful mutations do not await refresh, and duplicate BroadcastChannel/storage messages are nonce-deduplicated with bounded memory.
- Verification: 63 JavaScript tests, 58 PHP tests / 521 assertions on PHP 8.1 and 8.4, 13 Chromium scenarios, smoke cart snapshots `0 → 1 → 0`, Woo lifecycle, exact-ZIP WordPress 6.9/7.0.4/7.1 matrix, Playground execution, and Plugin Check `0 errors / 54 documented warnings / 0 trademark findings` pass.
- Final deterministic artifacts: plugin `e25c4dee2fa1b5d6456002798da3fac5c27a8a8d99f2e20af4a8d2a6164bdee0`; Playground `d666f777259aaa2738171aab0db093ddbf019752ff838c84a8cf3b86510db2ea`.
