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

- PHP: 65 tests / 597 assertions pass on PHP 8.1 and 8.4; Coding Standards has zero errors.
- Browser code: 66 JavaScript tests, 14 Chromium scenarios, lint, and dependency audit pass.
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
- Final deterministic artifacts: plugin `2da371cf494d2c4921e8c50f725136e4ebf509d44b312dc9dd827c311c4a962e`; Playground `9ca14aabc65fe77ffa98f088f549da26a43d2e68b53c17f76535b68a491b68c8`.
- Exact-ZIP WordPress 6.9/7.0.4/7.1 matrix, legacy/HPOS Woo lifecycle, smoke/security, 14 Chromium scenarios, Playground execution, and Plugin Check all pass. Plugin Check reports 0 errors, 54 documented warnings, and 0 trademark findings.

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
- Verification: 66 JavaScript tests, 65 PHP tests / 597 assertions on PHP 8.1 and 8.4, 14 Chromium scenarios, smoke cart snapshots `0 → 1 → 0`, Woo lifecycle, exact-ZIP WordPress 6.9/7.0.4/7.1 matrix, Playground execution, and Plugin Check `0 errors / 54 documented warnings / 0 trademark findings` pass.
- Final deterministic artifacts: plugin `2da371cf494d2c4921e8c50f725136e4ebf509d44b312dc9dd827c311c4a962e`; Playground `9ca14aabc65fe77ffa98f088f549da26a43d2e68b53c17f76535b68a491b68c8`.

## Agent Sessions replay reliability

- [x] Reproduce the workflow rows that fail to open and capture their error state.
- [x] Trace row activation, workflow explanation construction, and response-size enforcement.
- [x] Add positive, boundary, and negative regression coverage.
- [x] Implement bounded replay output with explicit truncation and visible failure feedback.
- [x] Run the full source/exact-artifact matrix and complete independent review.
- [x] Commit the Agent Sessions replay fix.

### Agent Sessions replay review

- Root cause: `explain_agent_workflow` allowed a 200-event timeline while every tool result had an intentional 8,192-byte ceiling; 18+ ordinary events crossed that limit, so larger rows selected but could not replace the prior replay.
- Fix: replay results fit within 7,000 bytes, retain the first 8 and latest 12 displayed events, and query first problem, first later recovery, and latest commerce evidence across the full scoped workflow—including histories beyond 200 events. Any omission is disclosed as a partial replay.
- UI behavior: only the selected row becomes busy; failures clear stale evidence, show a visible and announced error, restore the button, and never emit false result/update events. Late rapid-click responses cannot replace the latest selection.
- Verification: 66 JavaScript tests, 65 PHP tests / 597 assertions on PHP 8.1 and 8.4, 14 Chromium scenarios, bounded-replay smoke coverage, Woo lifecycle, exact-ZIP WordPress 6.9/7.0.4/7.1 matrix, Playground execution, and Plugin Check `0 errors / 54 documented warnings / 0 trademark findings` pass.
- Final deterministic artifacts: plugin `2da371cf494d2c4921e8c50f725136e4ebf509d44b312dc9dd827c311c4a962e`; Playground `9ca14aabc65fe77ffa98f088f549da26a43d2e68b53c17f76535b68a491b68c8`.

## Publish-ready hackathon showcase

- [x] Define the judge narrative, architecture, and coherent showcase scenarios.
- [x] Create and visually verify an editable Agent SNR presentation deck.
- [x] Build a separate clean showcase environment without modifying current development data.
- [x] Capture real showcase screenshots and align the deck, demo script, and operator runbook.
- [x] Verify the complete publishable package, independently review it, and commit.

### Publish-ready hackathon showcase review

- Story: the deck and runbook use one coherent order—evidence-first shopping, honest TerraRoll capability gap, checkout handoff, human no-charge order, Workflow Replay/attribution, session governance, and operator refund.
- Architecture: the 11-slide editable deck shows the two top-level WebMCP surfaces, same-origin WordPress execution boundary, human checkpoint, verified WooCommerce outcomes, redacted ledger, and session-only control loop. All slides render without overflow and contain portable source notes.
- Reproduction: `start-showcase.sh` auto-builds a missing deterministic ZIP, verifies its checksum/tree, binds only to loopback, uses the fixed `agent-snr-showcase` project, and preserves the development stack. Clean-clone auto-build reproduced SHA-256 `2da371cf494d2c4921e8c50f725136e4ebf509d44b312dc9dd827c311c4a962e` byte-for-byte.
- Evidence: the checked-in 1440×900 capture set records an eight-call workflow, out-of-stock TerraRoll 25 capability signal (`recorded=true`, `fulfilled=false`), human order, direct attribution, session control, full `$109.00` refund, `$0.00` net, and zero console errors.
- Safety: capture credentials fail closed; remote capture requires HTTPS, explicit opt-in, explicit credentials, and same-origin admin redirects. Public cookies are restored from the exact pre-login snapshot. Output promotion is marker-gated, failure-atomic, and rejects broad or unmarked directories.
- Verification: `npm run verify` passes 79 JavaScript/configuration tests plus launcher guard tests; exact artifact start/capture/verify/stop passes; Plugin ZIP and Playground checksums remain unchanged; the PPTX ZIP and slide-overflow checks pass; `git diff --check` is clean.
- Remaining publication gates are still owner/external: formal Agent SNR clearance, public remote, hosted top-level HTTPS deployment, real-client validation, hosted recapture/video, Devpost submission, and freeze.

## Mixed WooCommerce gateway compatibility

- [x] Reproduce and document the wp-admin fatal with an object-based third-party gateway.
- [x] Add focused regression coverage for mixed class-string/object gateway lists.
- [x] Replace unsafe whole-array deduplication with a strict demo-gateway presence check.
- [x] Run focused and full verification, then confirm the live admin dashboard recovers.
- [x] Complete independent review and commit the fix.

### Mixed gateway compatibility review

- Root cause: `woocommerce_payment_gateways` can contain both class strings and instantiated gateway objects. Default `array_unique()` coerced PayPal Commerce's non-stringable `AxoGateway` object and fatally broke wp-admin after the WooCommerce setup wizard activated optional payment extensions.
- Fix: Agent SNR now preserves third-party keys, order, objects, and duplicates; it recognizes its own class or instance with case-insensitive `is_a(..., true)` and appends only the missing demo gateway.
- Coverage: isolated regression tests cover mixed arrays, key/object identity, canonical and differently-cased class strings, existing demo objects, idempotence, demo-disabled mode, and missing WooCommerce dependencies.
- Live verification: the development dashboard recovered with WooCommerce PayPal Payments and WooPayments still active. The final exact ZIP also loaded PayPal Payments 4.1.2 gateway objects and completed the full no-charge order/refund capture with zero console errors.
- Gates: 79 JavaScript/configuration tests, 68 PHP tests / 606 assertions on PHP 8.1 and 8.4, 14/14 Chromium scenarios, smoke/security, legacy and HPOS Woo lifecycle, Plugin Check `0 errors / 54 warnings`, WordPress 6.9/7.0.4/7.1 exact-artifact matrix, and Playground execution pass.
- Final deterministic artifacts: plugin `6bc90a59837a46fbb350dda53cb3b503e60e11315f4a2c804f163d0888e27c89`; Playground `96bbd8ef4d09f392d8d9d0ebdddba30e34f0d232826bb9688f6243609cf3d978`.

## Agent Guide, Feedback, and Opportunity Signals

- [x] Specify friendly guide discovery, automatic opportunity triggers, feedback taxonomy, trust labels, metric enrichment, privacy, rate limits, and aggregation.
- [x] Implement Agent Guide exposure, report_agent_feedback, evidence linking, server-computed metrics, and automatic observed opportunity signals.
- [x] Add Agent Feedback and Opportunity Signals to the storefront and Agent SNR UI with accessible evidence-source labels.
- [x] Add PHP, JavaScript, browser, security, isolation, idempotency, and aggregation regression coverage.
- [x] Update the demo narrative, runbook, screenshots, presentation, product/submission docs, artifacts, and checksums.
- [x] Run full exact-artifact verification, independent review, and commit.

### Locked acceptance contract

- Catalog abilities: 13 storefront and 9 Agent SNR; public discovery exposes 12 and 8 respectively. `get_agent_guide`, `report_agent_feedback`, and `get_opportunity_signals` are canonical; two capability-gap abilities remain server-side compatibility paths.
- Discovery: storefront context points agents to a versioned, human-readable Agent Guide. The guide documents supported journeys, reversible actions, the human checkout boundary, privacy, feedback triggers, and the two-report limit.
- Observation: zero-result and constrained low-coverage searches create deterministic `site_observed` signals without storing raw prompts, raw queries, identity, addresses, or arbitrary attributes.
- Testimony: agent feedback is closed-schema, `agent_reported`, current-workflow scoped, limited to two unique reports, and linked only to same-session storefront evidence.
- Measurement: agents request allowlisted metric names only. Values come from site events, catalog facts, and attributed WooCommerce outcomes; unavailable conversion/value stays pending instead of becoming a false zero.
- Presentation: Agent SNR always separates site observation, agent testimony, and site-verified measurements, never calls opportunity context “lost revenue,” and stays current-demo-session scoped.
- Demo path: guide → zero IPX5 waterproof-backpack results under $100 → relaxed search with two IPX4 matches → HarborLite handoff → structured constraint feedback → human order → verified outcome in Signals/Replay.

### Agent Guide, feedback, and opportunities review

- Product: 12 storefront + 8 Agent SNR publicly discovered tools expose one canonical Agent Guide, automatic zero/low-coverage/OOS/missing-fact observation, evidence-linked structured feedback, dynamic site-computed metrics, replay evidence, and unified Signals; two legacy gap abilities remain undiscoverable.
- Trust/privacy: site-observed, agent-reported, and site-verified sources remain separate; raw prompt/search/free-form feedback and caller metric values are excluded; unknown demand uses keyed grouping; evidence is same-workflow scoped; two feedback slots are database-enforced.
- Demo: ten real 1440×900 exact-ZIP screenshots record Guide v1.0, a zero-result IPX5 signal, two IPX4 alternatives, linked feedback, a $69 HarborLite human order, direct attribution, governance, full refund, and $0 net with zero console errors.
- Presentation: the editable 11-slide deck is updated in place with the new architecture/demo/provenance story. Template/theme fidelity, speaker-note sources, package, placeholder, exported-render, and overflow checks pass.
- Verification: 108 JavaScript tests, 106 PHP tests / 1,940 assertions on PHP 8.1 and 8.4, 15/15 Chromium scenarios, 20 public + 2 legacy REST/security smoke, 11/11 native WebMCP smoke, Woo lifecycle, exact-ZIP WordPress 6.9/7.0.4/7.1 matrix, legacy/HPOS, Playground execution, dependency audit, and Plugin Check `0 errors / 73 reviewed warnings / 0 trademark findings` pass.
- Final deterministic artifacts: plugin `7cd4e74ca39c3a9dd4729a0deca3916585c87e2330cb6a65db4194d912a8ba5a`; Playground `3b5189dc558dddb6d87d323543e8bde02a6eab36240886874d4794dce76d398d`.
- Remaining gates are owner/external only: formal Agent SNR clearance, public remote/tag, hosted top-level HTTPS deployment, real ChatGPT/Chrome validation, final WooCommerce 11.1 rerun if released, hosted media/video, Devpost URLs/submission, and freeze.

## X-post hackathon readiness alignment

- [x] Verify the five referenced resources: WebMCP Workbench, Google WebMCP Evals CLI, WebMCP.com implementations/API, agent-journey guide, and resource directory.
- [x] Write one aligned product and technical design before changing implementation.
- [x] Audit the 22 catalog abilities, public surfaces, descriptions, journey, confirmation boundaries, visible state, eval coverage, and submission proof against that design.
- [x] Implement the smallest complete set of local improvements; keep extension installation, directory publication, hosted deployment, and real-client runs as explicit owner/manual gates.
- [x] Add formal tool-selection and full-workflow evals with deterministic fixtures and CI-safe execution.
- [x] Refresh the live demo, runbook, screenshots, deck, Devpost copy, verification report, exact artifacts, and checksums.
- [x] Run the full source/exact-artifact matrix, independent review, and commit.

### X-post hackathon readiness review

- Design: `submission/webmcp-readiness-design.md` maps the five resources into one co-browsing product contract, two outcome-oriented surfaces, zero Sensitive Action tools, explicit pricing/human boundaries, failure recovery, eval layers, and owner-only publication gates.
- Discovery: 12 storefront and 8 Agent SNR tools are publicly registered. `report_capability_gap` and `get_capability_gaps` remain registered with `wmcp.public=false`; they are absent from manifests, governance enums, visible Controls, and eval schemas. The handoff is publicly named `prepare_checkout_handoff`.
- Guide: Guide 1.1 publishes seven shopper steps, four operator steps, answer/action/telemetry/human-checkpoint/sensitive taxonomy, co-browsing-only scope, zero sensitive tools, optional feedback, privacy, and subtotal/final-total rules. Runtime/schema referential integrity is tested.
- Evals: exact `webmcp-evals@0.0.4`, generated schema parity, 17 fixture/report/smoke tests, natural-language selection/no-call/recovery suites, live browser trajectories with `ok:true` constraints, application-error-aware native smoke, and provenance-bound strict all-pass report checking are included. Model-backed runs remain an owner gate and no provider key was used.
- External handoff: Workbench 1.2.1 and WebMCP.com scanner/API/human-listing evidence templates contain release identity, security, approval, log, audit, replay, model, classification, and sign-off fields without fabricating results.
- Demo/media: ten fresh 1440×900 screenshots record Guide 1.1, 12/8 discovery, IPX5 missed demand, IPX4 recovery, feedback, human order, provenance, control, and refund with zero console errors. The editable 12-slide deck adds the WebMCP quality loop; theme fidelity, sources, placeholders, overflow, and every slide were verified.
- Verification: 108 JavaScript tests; 106 PHP tests / 1,940 assertions on PHP 8.1 and 8.4; 15/15 HPOS Chromium scenarios; 20-public + 2-legacy REST/security smoke; 11/11 adapted native WebMCP smoke; legacy/HPOS Woo lifecycle; exact-ZIP WordPress 6.9/7.0.4/7.1 matrix; Playground execution; zero npm vulnerabilities; and Plugin Check `0 errors / 73 reviewed warnings / 0 trademark findings` pass.
- Deterministic artifacts: plugin `7cd4e74ca39c3a9dd4729a0deca3916585c87e2330cb6a65db4194d912a8ba5a`; Playground `3b5189dc558dddb6d87d323543e8bde02a6eab36240886874d4794dce76d398d`.
- Remaining gates include the internal protected-model remediation and clean rerun documented below, plus external/owner actions for public remote/tag, formal Agent SNR clearance, frozen HTTPS deployment, real ChatGPT/Chrome, Workbench, scanner/directory review, final WooCommerce 11.1 if released, hosted media/video, Devpost submission, and freeze.

## Live model-backed WebMCP eval report

- [x] Record the exact commit, release artifact, provider/backend, fixed model version, fixtures, and run counts.
- [x] Re-run deterministic fixture/schema checks and adapted native Chrome smoke against the final showcase.
- [x] Run storefront and Agent SNR local tool-selection suites three times each.
- [x] Run the live result-aware shopper browser journey once and confirm no order is created.
- [x] Run the repository provenance checker for every JSON report and record each strict-gate verdict.
- [x] Publish a sanitized repository report with results, failures, limitations, and private evidence hashes.

### Live model-backed eval review

- The credential preflight succeeded without printing or committing the key. The run used fixed model `openai:gpt-5.4-mini-2026-03-17`, backend `vercel`, `webmcp-evals@0.0.4`, commit `e853062bb097e1c94fcb2a4fec64f019b3c9676b`, and exact plugin artifact SHA-256 `7cd4e74ca39c3a9dd4729a0deca3916585c87e2330cb6a65db4194d912a8ba5a`.
- Deterministic guards (17/17), generated schema parity, and adapted native Chrome smoke (5/5 storefront and 6/6 Agent SNR) pass.
- The protected model-backed gate **fails**: storefront selection passes 33/54 case-runs, Agent SNR selection passes 31/45, and the browser journey passes 3/8 required rows. All three strict provenance-checker invocations exit nonzero.
- Safety held despite the quality failures: every case with authored `expectedCall: null` made zero state-changing calls, and an operator-observed before/after query found that the live browser run created zero orders.
- The sanitized evidence report is `submission/webmcp-eval-report-2026-09-01.md`; raw reports remain private under ignored `.evals/`. Release clearance now requires the documented fixture/journey/feedback fixes and a clean rerun.
