# Submission-readiness plan

Source of truth: the supplied “WordPress WebMCP AgentOps — Full Hackathon Engineering & Submission Handoff,” verified through August 29, 2026.

## Phase 0 — Repository and contracts

- [ ] Initialize Git without rewriting later submission history.
- [ ] Add GPL-2.0-or-later license and hackathon disclosure.
- [ ] Define shared tool names, event names, risk classes, manifest/REST envelopes, DB version, and attribution rule version.
- [ ] Add dependency manifests, formatting/lint configuration, CI skeleton, and repository hygiene.
- [ ] Separate local build-complete requirements from owner/external submission actions.

## Phase 1 — One complete WebMCP slice

- [ ] Bootstrap/activate/deactivate/uninstall the plugin safely.
- [ ] Register WordPress Abilities on the canonical hooks.
- [ ] Create versioned, idempotent analytics schema migrations.
- [ ] Bootstrap an isolated demo session with a short-lived CSRF token.
- [ ] Serve a dynamic surface-scoped manifest.
- [ ] Register top-level imperative tools with `document.modelContext.registerTool()`.
- [ ] Execute through same-origin REST with permission, policy, validation, rate-limit, and event-integrity checks.
- [ ] Reflect successful calls in visible UI state.

## Phase 2 — Shopper workflow

- [ ] Implement context, product search/get/compare, policy, cart read/write, checkout handoff, and capability-gap tools.
- [ ] Seed deterministic fictional products, policy content, and original artwork.
- [ ] Build judge landing, storefront workflow rail, result/comparison/policy/cart panels, and accessibility behavior.
- [ ] Keep the human storefront usable without WebMCP.

## Phase 3 — Commerce and attribution

- [ ] Initialize and scope WooCommerce sessions safely.
- [ ] Add the explicit demo-only no-charge gateway.
- [ ] Link created/paid/cancelled/refunded orders through WooCommerce CRUD and HPOS-safe hooks.
- [ ] Implement direct/assisted/influenced attribution and gross/refund/net calculations.
- [ ] Preserve evidence and prevent revenue double counting.

## Phase 4 — AgentOps and governance

- [ ] Implement overview, funnel, workflow explorer/explanation, tool health, and capability-gap queries.
- [ ] Build public current-session AgentOps and authenticated admin surfaces.
- [ ] Implement session-scoped `set_tool_enabled`, persistent admin policy, and global kill switch.
- [ ] Refresh registrations after manifest invalidation and enforce changes server-side immediately.

## Phase 5 — Verification and hardening

- [ ] Unit-test deterministic core services.
- [ ] Integration-test WordPress, REST, WooCommerce, HPOS on/off, isolation, and reset behavior.
- [ ] Browser-test progressive enhancement, top-level/iframe behavior, cancellation, UI synchronization, and governance refresh.
- [ ] Security-test schemas, CSRF/origin/replay, cross-session access, PII redaction, cache safety, and kill switch.
- [ ] Run formatters, static analysis, tests, builds, and install-from-ZIP smoke checks.
- [ ] Review the final diff and resolve all release-blocking findings.

## Phase 6 — Reproduction and submission package

- [ ] Provide one-command Docker reproduction and idempotent bootstrap/reset scripts.
- [ ] Build and validate the installable plugin ZIP.
- [ ] Build and validate the WordPress Playground Blueprint bundle.
- [ ] Add CI and tagged-release automation.
- [ ] Finish README, SECURITY, CONTRIBUTING, TESTING, notices, changelog, Devpost copy, judge instructions, screenshots plan, video script, and final checklist.
- [ ] Mark all external owner actions explicitly: final name, public Git host/slug, live HTTPS deployment, real-client validation, screenshots, public YouTube upload, and Devpost submission/freeze.

## Review

Pending implementation and verification.
