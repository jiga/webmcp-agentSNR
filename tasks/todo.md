# Submission-readiness plan

## Live purchase — waterproof backpack under $90

- [x] Inspect the live catalog and verify waterproofing, price, and stock.
- [x] Select the strongest eligible backpack without exceeding the budget.
- [x] Add the item to the cart and verify the cart contents and total.
- [ ] Complete the available checkout flow, stopping only for missing personal or payment details.
- [ ] Record the final outcome and evidence in this section's review.

### Live purchase review

HarborLite 16 Pack selected at $69: in stock, IPX4, 16 L, 0.62 kg, 13-inch laptop fit, and a 30-day return window. The normal cart visibly contains one unit at a $69 subtotal. Checkout is prepared with the site's demo identity and a non-personal demo email; the page shows a $69 total and the no-charge demo payment method. Final order placement is paused at the required human confirmation boundary.

Source of truth: the supplied full WordPress WebMCP hackathon engineering and submission handoff, verified through August 29, 2026.

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

## Phase 4 — Agent SNR and governance

- [x] Implement overview, funnel, workflow explorer/explanation, tool health, and capability-gap queries.
- [x] Build public current-session Agent SNR and authenticated admin surfaces.
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

Remaining work is exclusively owner/external: complete formal Agent SNR trademark/domain clearance, upload the finished demo video to public YouTube, finish the remaining Devpost fields and personal declarations, submit, and freeze. The public repository, HTTPS deployment, official real-client validation, public URLs, and local video artifact are complete. A WooCommerce 11.1 run is optional newest-release evidence if a stable build is available and time permits.

## Agent Experience Monitoring iteration

- [x] Compare established session replay, digital experience monitoring, and agent observability products.
- [x] Define the primary operator, category position, core monitoring objects, and explicit privacy boundary.
- [x] Reframe the monitoring surface around Monitor, Agent Sessions, Journey, Tools, Signals, and Controls.
- [x] Add an agent journey model strip and a truthful loaded-snapshot Signals view using existing redacted evidence.
- [x] Rename the public surface away from an existing market association while preserving the then-current pre-release technical identifiers.
- [x] Update product positioning and judge-facing documentation.
- [x] Run JavaScript/PHP/browser checks and complete independent review; commit the iteration.

## Agent SNR brand finalization

- [x] Select Agent SNR as the final public submission brand.
- [x] Replace public product-name references across plugin UI, package metadata, documentation, and submission copy.
- [x] Preserve the then-current pre-release technical identifiers during that public-copy-only brand pass.
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
- Remaining publication gates are still owner/external: formal Agent SNR clearance, hosted recapture/video, Devpost submission, and freeze; the public repository, HTTPS deployment, and official in-app browser validation are live.

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
- Remaining gates are owner/external only: formal Agent SNR clearance, public YouTube upload, remaining Devpost form fields/submission, and freeze. The public repository, HTTPS deployment, official real-client path, gallery, thumbnail asset, and local demo video are complete; a release tag and final WooCommerce 11.1 rerun are optional evidence.

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
- Evals: exact `webmcp-evals@0.0.4`, generated schema parity, natural-language selection/no-call/recovery suites, live browser trajectories with `ok:true` constraints, application-error-aware native smoke, and provenance-bound strict all-pass report checking are included. The protected fixed-model rerun now clears the strict gate; credentials and raw reports remain private.
- External handoff: Workbench 1.2.1 and WebMCP.com scanner/API/human-listing evidence templates contain release identity, security, approval, log, audit, replay, model, classification, and sign-off fields without fabricating results.
- Demo/media: ten fresh 1440×900 screenshots record Guide 1.1, 12/8 discovery, IPX5 missed demand, IPX4 recovery, feedback, human order, provenance, control, and refund with zero console errors. The editable 12-slide deck adds the WebMCP quality loop; theme fidelity, sources, placeholders, overflow, and every slide were verified.
- Verification: 108 JavaScript tests; 106 PHP tests / 1,940 assertions on PHP 8.1 and 8.4; 15/15 HPOS Chromium scenarios; 20-public + 2-legacy REST/security smoke; 11/11 adapted native WebMCP smoke; legacy/HPOS Woo lifecycle; exact-ZIP WordPress 6.9/7.0.4/7.1 matrix; Playground execution; zero npm vulnerabilities; and Plugin Check `0 errors / 73 reviewed warnings / 0 trademark findings` pass.
- Deterministic artifacts: plugin `7cd4e74ca39c3a9dd4729a0deca3916585c87e2330cb6a65db4194d912a8ba5a`; Playground `3b5189dc558dddb6d87d323543e8bde02a6eab36240886874d4794dce76d398d`.
- Remaining gates are external/owner actions for formal Agent SNR clearance, public YouTube upload, Devpost submission, and freeze. The public repository, frozen HTTPS deployment, official real-client path, and local hosted-demo video are complete; a tag, Workbench, scanner/directory review, and a WooCommerce 11.1 rerun are optional evidence if pursued.

## Live model-backed WebMCP eval report

- [x] Record the exact commit, release artifact, provider/backend, fixed model version, fixtures, and run counts.
- [x] Re-run deterministic fixture/schema checks and adapted native Chrome smoke against the final showcase.
- [x] Run storefront and Agent SNR local tool-selection suites three times each.
- [x] Run the live result-aware shopper browser journey once and confirm no order is created.
- [x] Run the repository provenance checker for every JSON report and record each strict-gate verdict.
- [x] Publish a sanitized repository report with results, failures, limitations, and private evidence hashes.

### Initial live model-backed eval review — superseded baseline

- The credential preflight succeeded without printing or committing the key. The run used fixed model `openai:gpt-5.4-mini-2026-03-17`, backend `vercel`, `webmcp-evals@0.0.4`, commit `e853062bb097e1c94fcb2a4fec64f019b3c9676b`, and exact plugin artifact SHA-256 `7cd4e74ca39c3a9dd4729a0deca3916585c87e2330cb6a65db4194d912a8ba5a`.
- Deterministic guards (17/17), generated schema parity, and adapted native Chrome smoke (5/5 storefront and 6/6 Agent SNR) pass.
- The initial protected model-backed gate **failed**: storefront selection passed 33/54 case-runs, Agent SNR selection passed 31/45, and the browser journey passed 3/8 required rows. All three strict provenance-checker invocations exited nonzero.
- Safety held despite the quality failures: every case with authored `expectedCall: null` made zero state-changing calls, and an operator-observed before/after query found that the live browser run created zero orders.
- Those findings became the remediation acceptance criteria. The sanitized evidence report at `submission/webmcp-eval-report-2026-09-01.md` now records both this superseded baseline and the passing rerun; raw reports remain private under ignored `.evals/`.

## Model-backed eval remediation

- [x] Reproduce and classify every failed storefront, Agent SNR, and browser row without weakening the authored safety or outcome requirements.
- [x] Make isolated selection fixtures deterministic about guide start state, tool result context, and the intended single decision boundary.
- [x] Reproduce the browser-only `invalid_agent_feedback` sequence, fix the underlying workflow/evidence validation defect, and add positive/negative regression coverage.
- [x] Refine the live shopper journey so required commerce outcomes remain primary and optional telemetry occurs only after the core path.
- [x] Run focused tests, the full deterministic/native matrix, and the same fixed-model protected suites with strict provenance checking.
- [x] Update the sanitized report and release checklists with the rerun evidence; complete an independent review and focused commit.

### Model-backed eval remediation review

- Final provenance is commit `e4d9c86b2754c735094b1dc8437fbd007d3e557a`, plugin SHA-256 `38b1b8106f255b051ff07c953afb6db3be4504df85ac1c4f454c69ec82a416fa`, and Playground SHA-256 `96a9ec44596a76a07e5d549ff486d35acd8c0292cd5878db6e361321664d3f89`.
- The fixed-model protected gate passes storefront 54/54, Agent SNR 45/45, and live browser 8/8 with zero console errors and no new WooCommerce order; all three strict provenance checkers pass.
- The complete deterministic matrix passes: 112 JavaScript tests, 111 PHP tests / 1,964 assertions on PHP 8.1 and 8.4, 16/16 Chromium scenarios, 11/11 adapted native smoke calls, and 21/21 eval fixture/patch/checker/smoke guards.
- [`submission/webmcp-eval-report-2026-09-01.md`](../submission/webmcp-eval-report-2026-09-01.md) records the before/after results, remediation, safety evidence, limitations, and private evidence hashes. The initial failed run remains a superseded baseline rather than an active blocker.

## Devpost final submission compliance

- [x] Capture the September 2 official Rules, Requirements, FAQ, deadline, judging, testing-access, IP, and post-deadline freeze obligations without relying on the optional Devpost plugin.
- [x] Create a rule-by-rule checklist that separates verified repository evidence from entrant declarations and external account actions.
- [x] Audit the public-repository package for complete source/assets/instructions, visible open-source licensing, native `document.modelContext.registerTool()` evidence, English-language materials, third-party rights, and pre-existing-versus-new work disclosure.
- [x] Reconcile the Devpost description, testing instructions, demo script, media plan, URLs, credentials guidance, and judging-criteria narrative with the official requirements.
- [x] Add a final freeze procedure covering the public repository, deployed code/configuration and seed content, video, Devpost entry, frozen public commit, optional tag/release, free availability through September 21, 2026 at 5:00 p.m. PT, and no entrant-controlled submitted changes until the winner announcement around September 23.
- [x] Run the complete local validation appropriate to changed artifacts, independently review the compliance package, and commit it without claiming unperformed owner actions.

### Devpost final compliance review

- Rules: the Official Rules, Overview, FAQ, and current Devpost form guidance were reviewed September 2 and rechecked September 3. The central checklist separates official requirements, project quality policy, optional evidence, and declarations/actions that only the entrant can complete.
- Package: README, hackathon provenance, security/testing guidance, notices, Devpost story, marked testing-instructions copy, owner runbook, media plan, video script, presentation, optional external-evidence templates, and the rule-by-rule checklist agree on one submission contract.
- Verification: 112/112 JavaScript/configuration tests, showcase launcher guards, schema parity, 45/45 local Markdown links, JSON parsing, diff hygiene, credential-pattern scan, and plugin/Playground package integrity pass. Two consecutive current pre-owner builds match (`24a6a9152f814433877a90fc6c82ac23f6d42787faae9df154b68854fbfef704` plugin; `75436495986664e96dcd9b50a73c1060a622628b6064aa6b1ded12538041c6cc` Playground).
- Evidence boundary: current package hashes and the complete exact-ZIP/PHP/Woo/Chromium/native matrix cover the renamed artifact; protected model evidence is bound to rename commit `410c198963ec649ed58e21fce7c80103db3d0ad8`. Hosted persistence and official-client evidence are bound to deployed public commit `e46c9c539ea649a7701e59bad9784cb7012be5b9`.
- Presentation: the 12-slide deck passed independent visual, overflow, package, theme, placeholder, notes/source, OCR, and template-fidelity review; after the post-rename screenshot, note-path, and 125-test proof updates, SHA-256 is `1b0a1b95d18908c1d1f712b8ba35cca752a035f1f898cbd6470d0de214b49c1a`.
- Independent review: compliance and deck reviewers approved with no remaining repository-local finding.
- Honest blockers: the public repository, HTTPS demo, and real-client hosted result are verified; no optional tag, public YouTube video, final form entry, entrant eligibility/authority/ownership attestation, contributor identity, or name/mark clearance is fabricated. Those remaining items stay unchecked and must be completed before the deadline.

## Render production deployment

- [x] Confirm the current Render account/credit state and the Git-hosting path without exposing credentials or redemption codes.
- [x] Lock a production topology that preserves WordPress, WooCommerce, MySQL, persistent uploads, same-origin REST, top-level HTTPS WebMCP, and judge-session state.
- [x] Add the smallest production-safe Render Blueprint/container/bootstrap files with generated secrets, final-URL handling, cache/header safety, idempotent seeding, and no local development defaults.
- [x] Fix the showcase refund-evidence wait so regenerated public screenshots cannot capture whitespace-wrapped placeholder values; add regression coverage and recapture the final outcome.
- [x] Validate configuration syntax, deterministic plugin packaging, container startup/bootstrap behavior, persistence assumptions, health endpoints, and local regression checks.
- [x] Publish the required public repository with approved author identity and unsquashed history; verify public `main`, GPL-2.0 detection, anonymous README access, description/topics, and secret scanning.
- [x] Deploy frozen public commit `e46c9c539ea649a7701e59bad9784cb7012be5b9` to the paid Render Blueprint after explicit cost approval and user-managed card entry.
- [x] Verify the public URLs without authentication, confirm HTTPS/same-origin/readiness, test 12 storefront and 8 Agent SNR tools in the ChatGPT in-app browser, and update submission URLs/evidence.

### Render deployment review

- Account: the intended Render Hobby workspace contains the $50 Hackathon Participant credit, valid through August 31, 2027. The user added the required payment method directly in Render; no payment data or credit/redemption value is stored or logged by the repository.
- Architecture: one `1c-2g` public WordPress service and one `1c-2g` private MariaDB service run in Oregon with automatic deploys/previews disabled. Only uploads and MariaDB data persist; application code comes from pinned images and the frozen Git commit on every start.
- Supply chain: WordPress 7.1/PHP 8.3, WP-CLI 2.12, and MariaDB 11.8 images are digest-pinned. WooCommerce 11.0.1 is downloaded only over HTTPS/TLS with retries and must match SHA-256 `da189b6616c610d15a2106f93151dab81b78f83e075bcefce221ac0d00b4fa21` before extraction.
- Bootstrap: Render-generated database/admin/salt secrets stay out of Git; proxy-aware HTTPS, same-origin URLs, production/demo constants, bounded DB wait, least-privilege WP-CLI, version checks, activation, and idempotent seeding all fail closed before Apache starts.
- Public evidence: the unresolved contributor placeholder and former demo-store candidate name are removed. Ten 1440×900 screenshots were regenerated with zero console errors; the refund frame verifies one paid order, `$69.00` refunded, and `$0.00` net. The capture wait now rejects trimmed placeholder values.
- Verification: official Blueprint schema, Bash syntax, build-context allowlist, npm audit, 125/125 JavaScript tests, 111 PHP tests / 1,964 assertions, deterministic plugin/Playground builds, both Docker builds, exact baked plugin contents, and two cold web starts against one persistent MariaDB pass. The second start preserves 12 products and 10 pages; health and storefront return HTTP 200.
- Presentation: all 12 slides were re-rendered and inspected after replacing all four embedded screenshots, updating the source-note paths and proof count, and removing retired identifiers; theme/fidelity/notes/placeholders/overflow/package/OCR pass. SHA-256 is `1b0a1b95d18908c1d1f712b8ba35cca752a035f1f898cbd6470d0de214b49c1a`.
- Hosted result: `agent-snr-production` deployed commit `e46c9c5` to `https://agent-snr.onrender.com/`. Both `1c-2g` services reached Live. A manual second web deploy preserved 12 products and 13 pages, created zero duplicate products, updated the 12 deterministic records, and returned the identical health-response SHA-256 before and after restart.
- Official-client result: the ChatGPT in-app browser discovered 12 storefront and 8 Agent SNR tools. Safe guide/context/overview/diagnostics/workflow calls returned `ok: true`, and the monitor correlated the same storefront workflow with two successful calls.
- Pending external gates: complete formal name/rights/entrant declarations, upload and review the finished MP4 on public YouTube, finish the Devpost form, submit, and freeze. Optional Workbench/scanner/directory/tag evidence remains non-blocking.

## Canonical Agent SNR technical-identity cleanup

- [x] Inventory every retired branded identifier across source paths, PHP namespaces/classes, plugin slug, REST namespaces/routes, options, hooks, JavaScript handles, package/build names, Docker/Render configuration, tests, artifacts, documentation, screenshots, and presentation assets.
- [x] Lock the canonical mapping: `Agent SNR` for display copy; `AgentSNR`, `agentSNR`, and `agentsnr` for code forms; and `wmcp-agentsnr`, `wmcp_agentsnr`, and `WMCP_AGENTSNR` for plugin/runtime forms. Keep neutral database, order metadata, demo-session, capability, and tool contracts unchanged.
- [x] Rename tracked source directories/files with history-preserving moves and update PHP/JavaScript/runtime contracts while preserving neutral persistence and security behavior.
- [x] Update tests, Docker/Render deployment, artifact builders, demo launchers, submission copy, package/checksum references, and the intended public repository URL to `https://github.com/jiga/webmcp-agentSNR`.
- [x] Regenerate the exact renamed-build screenshots and presentation, including speaker-note source paths, before public release.
- [x] Run case-insensitive residue checks, full source/PHP/browser/package/Render validation, two-start persistence tests, fresh screenshots/deck QA, and independent code/compliance review.
- [x] Commit the verified rename before creating or pushing the public repository; keep the Render deployment paused until the user approves the reviewed paid Blueprint.

### Agent SNR identity-cleanup review

- Source identity: tracked plugin, template/runtime/test, eval, package, CI-artifact, REST, option, hook, shortcode, surface, hash/log, Docker, and submission paths now use the locked Agent SNR forms. The Render repository URL and one-click deploy link target `https://github.com/jiga/webmcp-agentSNR`.
- History boundary: the current release tree and all post-rename commits contain no retired identifier. The owner explicitly approved preserving 19 earlier commit trees and one earlier commit subject with the development codename for timestamped hackathon provenance.
- Persistence boundary: `wmcp_workflows`, `wmcp_events`, `wmcp_order_links`, `wmcp_capability_gaps`, neutral `_wmcp_*` metadata, `wmcp_demo_session`, `manage_wmcp_*` capabilities, and public tool names remain unchanged.
- Focused verification: PHP syntax passes; PHPUnit passes 111 tests / 1,964 assertions; JavaScript/configuration passes 125 tests; JavaScript/CSS lint and Coding Standards pass with zero errors; schema parity and Render tests pass; two renamed plugin/Playground builds are byte-identical (`514a7f86fe4fadb0d3786ded3a58017a4be0c26546f5925533bd8e1d31a58943` and `fc260b06107aa040e8875ec94f8850a4c207720b75d38e326ef3b29aa80de280`).
- Evidence refresh: a clean exact-ZIP showcase reset and recapture, REST/security smoke, 16/16 Chromium scenarios, 11/11 native WebMCP calls, legacy and HPOS Woo lifecycle, four-image deck refresh, speaker-note path update, and full deck QA pass. The same renamed ZIP also passes the isolated WordPress 6.9 / WooCommerce 10.9.4 legacy, WordPress 7.0.4 / WooCommerce 11.0.1 legacy, and WordPress 7.1 / WooCommerce 11.0.1 HPOS matrix. Retired ignored ZIPs were quarantined outside `dist/`.
- Protected evals: the first rename-bound storefront run stopped at 52/54 after exposing inconsistent authored cart state and an unresolved-pronoun selection. Commit `410c198963ec649ed58e21fce7c80103db3d0ad8` supplies coherent optimistic state without relaxing expected calls; the final fixed-model gate passes storefront 54/54, Agent SNR 45/45, and browser 8/8 with all three strict checkers, zero console errors, and no new order.

## Hosted Devpost demo video

- [x] Lock a sub-three-minute storyboard that shows the hosted project working in the first 15 seconds, explains WebMCP, preserves the human checkout boundary, and closes on verified Agent SNR outcomes.
- [x] Build a deterministic hosted-site recording script with no credentials, customer data, admin UI, billing UI, live typing, or third-party media.
- [x] Record the actual storefront and monitoring surfaces at a legible 16:9 viewport, using visible live state transitions and concise chapter cards.
- [x] Generate original English narration, synchronize it to the recorded flow, and mix it into one public-upload-ready MP4.
- [x] Verify duration under three minutes, audible narration, video/audio streams, resolution, frame cadence, public-URL legibility, console cleanliness, and final-frame branding.
- [x] Update the video script/evidence record, commit the reproducible source and final artifact metadata, and provide the MP4 for YouTube upload.

### Hosted video review

- Source: `demo/record-hosted-demo.mjs` records a fresh private session against `https://agent-snr.onrender.com/`, invokes the registered page tools, uses the no-charge human checkout, and captures the storefront, monitoring, replay, signals, verified outcome, and session-control surfaces.
- Output: ignored release artifact `dist/agent-snr-devpost-demo.mp4`; duration 149.64 seconds, 1920×1080 progressive H.264 High, 25 fps, stereo AAC narration, 10.29 MB, SHA-256 `f9e2d89e586e2323a7e96c6d7623e0923cf69fd215fd511f631fc9a2c8f800d8`.
- Audio: original English Samantha system narration, no music, mean volume −18.9 dB, peak −4.1 dB, and no detected silence longer than three seconds at −45 dB.
- Visual QA: ten-frame contact sheet and final-frame inspection show actual hosted pages, legible chapter overlays, fictional checkout data, verified $69 paid outcome, Workflow Replay, Signals, and the disabled comparison control; no admin, billing, credential, notification, or unrelated browser UI appears.
- Publication gate: the owner must upload the MP4 as Public on YouTube, verify the processed duration/audio/captions logged out, and paste the final URL into Devpost before submission.

## Natural narration remaster

- [x] Generate nine conversational narration clips with scene-specific timing and delivery direction.
- [x] Fit clips to the existing chapter boundaries without truncation or unnatural speed changes.
- [x] Replace the system voice, normalize the mix, and preserve the verified video stream and sub-three-minute runtime.
- [x] Verify scene timing, loudness, silence, encoding, and final playback artifact; retain the entrant's subjective listen as the upload gate.
- [x] Update the video evidence and publish the reproducible remaster workflow.

### Natural narration review

- Preferred artifact: ignored release output `dist/agent-snr-devpost-demo-natural.mp4`; 149.64 seconds, 1920×1080 H.264 at 25 fps, stereo 48 kHz AAC, 10,308,584 bytes, SHA-256 `0d9da111b96ea8013c090cd05611a1a8dbb96a9dbe096c69fbb11bbde079edac`.
- Voice workflow: `demo/remaster-natural-narration.mjs` uses OpenAI `gpt-4o-mini-tts` with the recommended `marin` voice and unique conversational direction for each of nine scenes. Raw scene deliveries ranged from 12.2 to 19.45 seconds; only four required acceleration, and the maximum adjustment was 1.082×.
- Mastering: each clip begins with 300 ms of breathing room, is padded rather than stretched when short, normalized to −16 LUFS target, and fitted to the existing 14/15/16/17/19/15/17/20/16-second chapter boundaries. The original verified H.264 video stream is copied unchanged.
- Technical QA: runtime remains 149.64 seconds; mean volume is −19.1 dB, peak −1.3 dB, and no silence longer than three seconds appears at −45 dB. The owner must listen once, disclose that the voice is AI-generated, and upload this preferred natural cut rather than the earlier system-voice cut.

## Judge-facing two-persona WebMCP demo

- [x] Diagnose the walkthrough-vs-demo gap and lock a two-persona judging story before implementation.
- [x] Add a truthful live agent panel driven by the same registered tool invocations as the hosted page.
- [x] Record the shopper-agent journey with discovery, recovery, evidence, feedback, and human checkout.
- [x] Record the owner-agent journey with analytics, attribution, opportunity investigation, replay, and bounded governance.
- [x] Add the concise WordPress-plugin/WebMCP architecture explanation and natural timed narration.
- [x] Verify runtime, tool/result truthfulness, visual legibility, audio, third-party-media hygiene, and all repository checks.
- [x] Replace the preferred submission artifact and update the Devpost evidence record.

### Two-persona demo review

- Preferred artifact: ignored release output `dist/agent-snr-devpost-demo-v2.mp4`; 169.36 seconds, 1920×1080 H.264 at 25 fps, stereo 48 kHz AAC, 13,596,313 bytes, SHA-256 `7bcce14360a2238568854f5f455487215a3e2129a812413838d43cac04a528aa`.
- Shopper proof: the visible agent panel shows the full shopper request, discovery, `get_agent_guide`, cart state, IPX5 zero-result search, recovery search, comparison, policy, cart mutation, checkout handoff, bounded feedback, structured results, and agent decisions. The human alone clicks Place order.
- Owner proof: the panel switches to the store-owner request and shows analytics, conversion, health, workflow, opportunity, explanation, and session-control tool calls against Agent SNR, including actual result summaries and the linked converted workflow.
- Architecture proof: the first sixteen seconds explain the WordPress plugin, top-level WebMCP registration, same-origin PHP execution, WooCommerce outcome source, and redacted ledger while the hosted product is already working.
- QA: ten-frame inspection shows legible prompt/call/result/decision pairing with corresponding live UI state; no browser chrome, admin, billing, credential, notification, third-party logo, or unrelated content appears. Audio measures −19.2 dB mean / −1.5 dB peak with no silence longer than three seconds. Runtime leaves 10.64 seconds below the limit.
- Publication gate: entrant performs a subjective full listen, clears necessary descriptive compatibility references, discloses AI narration, uploads this v2 cut publicly to YouTube, and verifies the processed runtime and embed logged out.

## Pitch-card opening

- [x] Inspect the existing hackathon deck and select only the title, problem, and architecture frames.
- [x] Replace the first 10.5 seconds of the v2 cut with the selected deck frames without increasing runtime.
- [x] Rewrite and regenerate the opening narration so the problem, product, and architecture align with the cards.
- [x] Reveal the working hosted demo before 15 seconds and preserve the complete two-persona WebMCP demonstration.
- [x] Verify slide legibility, audio synchronization, runtime, encoding, and final full-video contact sheet.
- [x] Update the preferred artifact evidence and publish the reproducible edit.

### Pitch-card review

- Preferred artifact: ignored release output `dist/agent-snr-devpost-demo-v3.mp4`; 169.40 seconds, 1920×1080 H.264 at 25 fps, stereo 48 kHz AAC, 12,899,034 bytes, SHA-256 `016a6d68c2ef03c1b03912e285e840d37b2e3bdbba5457cd4b68905f72d45aa3`.
- Opening: deck slides 1, 2, and 4 run for 3.5 seconds each. They establish the name, the missing-signal problem, and the plugin/WebMCP architecture. The live hosted Agent SNR product appears at 10.5 seconds.
- Preservation: the edit replaces the original opening footage instead of adding runtime. Every shopper-agent, human-checkpoint, and owner-agent scene from the verified v2 demonstration remains present.
- QA: opening and full-video contact sheets confirm readable 16:9 cards, clean transition to the working product, complete visible tool/result pairing, and no unrelated UI or third-party logos. Audio measures −19.1 dB mean / −1.8 dB peak with no silence over three seconds.
- Publication gate: upload `agent-snr-devpost-demo-v3.mp4`, not the earlier cuts; perform a subjective listen and compatibility-name clearance, disclose the AI narration, and verify public YouTube playback and runtime logged out.

## Future-world narrative cut

- [x] Lock one story arc from agentic-web context through shopper and owner outcomes before editing.
- [x] Design a calm opening around the world where people delegate browsing and shopping to personal agents.
- [x] State the site-owner visibility problem and introduce Agent SNR as the missing WordPress operations layer.
- [x] Author opening narration and visual beats together with explicit holds, crossfades, and a natural handoff to the live demo.
- [x] Preserve the real shopper-agent, human-checkpoint, and owner-agent WebMCP proof within a sub-three-minute runtime.
- [x] Render and inspect opening frames, transition frames, full-video contact sheet, audio alignment, loudness, silence, and encoding.
- [x] Replace the preferred submission artifact, update evidence, verify the repository, and publish the reproducible edit.

### Future-world narrative review

- Preferred artifact: ignored release output `dist/agent-snr-devpost-demo-final.mp4`; 167.00 seconds, 1920×1080 H.264 at 25 fps, stereo 48 kHz AAC, 13,922,187 bytes, SHA-256 `309fa6f26cb2b580342b3cd3166d51a50cfb95f749383d3388f934b6eaa030ae`.
- Opening: a 5.5-second real zero-result WebMCP cold open leads into future-world, owner-blind-spot, and Agent SNR cards. All cards share one warm-white visual system and use 500 ms dissolves. The solution card embeds the exact first live storefront frame, producing a match dissolve instead of a reset.
- Story: the final causal chain is shopper intent → WebMCP action → missed demand → controlled recovery → human order → owner-agent evidence → IPX5 inventory decision. Session governance is no longer the closing beat because it does not resolve the demonstrated shopper need.
- Audio: twelve separately directed natural narration beats start after short visual holds and all run at natural 1.0× tempo. Loudness measures −19.3 dB mean / −1.5 dB peak, with no detected silence longer than three seconds.
- Reproduction: `npm run video:build-final` now drives capture, measured timeline, cards, visual composition, and narration in one clean-output chain. TTS cache keys bind text, direction, model, and voice; the timing report binds the exact source/output hashes. Every generated target refuses overwrite by default.
- Visual QA: the 35-frame focused opening review confirms that no dissolve cuts a thought, product evidence appears in the first frame, the cards remain readable, and the match dissolve lands on the same live storefront. Full and ending contact sheets confirm the complete shopper, human, owner, replay, and business-decision path with no admin, billing, credentials, browser chrome, third-party logos, or unrelated content.
- Publication gate: the owner performs one subjective full listen, clears descriptive compatibility references, discloses AI narration, uploads `agent-snr-devpost-demo-final.mp4` publicly to YouTube, and verifies processed runtime, audio, captions, and Devpost embed logged out.

## Context-first presentation cut

- [x] Remove the unexplained zero-result cold open from the locked story.
- [x] Restore a deliberate Agent SNR title slide before any product evidence.
- [x] Present the agent-operated future, owner visibility problem, and WordPress/WebMCP solution in that order.
- [x] Match-dissolve the solution slide into the complete shopper and owner demonstration.
- [x] Regenerate narration around the presentation beats at natural tempo and verify synchronization.
- [x] Re-run visual, audio, runtime, provenance, and repository checks; replace the preferred artifact and publish the reproducible edit.

### Context-first presentation review

- Preferred artifact: ignored release output `dist/agent-snr-devpost-demo-final.mp4`; 165.52 seconds, 1920×1080 H.264 at 25 fps, stereo 48 kHz AAC, 13,783,232 bytes, SHA-256 `a01396b39444a1b44051e7de83dc4661c470f87f3a3b57309f24f3f81e118ccc`.
- Presentation order: Agent SNR title → a web where every person has an agent → the site-owner blind spot → Agent SNR as the WordPress operations layer → live shopper and owner proof. No telemetry or unexplained result appears before the problem and solution are established.
- Synchronization: the title, future, problem, and solution narration occupy their own 4.5/5.5/6.5/7-second budgets. Each sentence ends before its dissolve; the solution card uses the exact live frame and hands narration to the shopper scene at 23.5 seconds.
- QA: 32 focused opening frames confirm readable holds, 500 ms dissolves, no context jump, and a natural transition into the shopper prompt. Audio measures −19.1 dB mean / −1.4 dB peak with no detected silence longer than three seconds. Runtime leaves 14.48 seconds of margin.
- Publication gate: the owner performs one subjective full listen, clears descriptive compatibility references, discloses AI narration, uploads the context-first final MP4 publicly to YouTube, and verifies processed runtime, audio, captions, and Devpost embed logged out.

## Narration continuity polish

- [x] Replace the abrupt “It calls search products” scene opening with a context-carrying sentence.
- [x] End the preceding guide scene by naturally cueing the catalog search.
- [x] Reframe checkout as intentionally outside the WebMCP toolset for human review and confirmation.
- [x] Regenerate only the affected narration, preserve natural 1.0× delivery, and verify no silence longer than three seconds.
- [x] Replace the preferred MP4 and refresh its metadata and submission evidence.

### Narration continuity review

- Preferred artifact remains `dist/agent-snr-devpost-demo-final.mp4` at 165.52 seconds; size 13,791,177 bytes; SHA-256 `bf0faf8744b4ef731c306b70724f9cdfc5fe071e1bf3b8fe7c778ddf4a3dc71c`.
- The 0:39 transition is now one continuous thought: the guide scene ends with “With those boundaries clear, the agent begins searching,” followed by “The request uses the shopper's exact constraints and succeeds, but returns zero matches.”
- The handoff story now says: “Checkout sits outside the WebMCP toolset, so the shopper reviews and confirms the order.” It presents human verification as an intentional trust boundary rather than a missing tool.
- All affected narration renders at natural 1.0× tempo. Final audio measures −19.1 dB mean / −1.3 dB peak with no detected silence longer than three seconds.

## Reusable hackathon submission skill

- [x] Extract stable, generic lessons from product design, rules compliance, repository preparation, deployment, evals, Devpost copy, media, and submission freeze.
- [x] Verify current official Devpost overview, rules, resources, and submission-help links; keep event-specific facts out of reusable requirements.
- [x] Create a discoverable personal skill with concise routing and detailed conditional references.
- [x] Add reusable readiness checklists, owner-only declaration boundaries, evidence/provenance guidance, and a video pitch-and-demo workflow.
- [x] Add and test a deterministic video-validation helper for runtime, streams, loudness, silence, size, and checksum.
- [x] Validate the skill structure and conduct a realistic forward-read for clarity, scope, and actionability.
- [x] Document the reusable artifact in this project and publish the repository-side record.

### Reusable skill review

- Installation: `/Users/jignesh/.codex/skills/hackathon-submission-readiness`, with automatic discovery enabled through a discriminating description.
- Structure: a 163-line entrypoint routes to five focused references covering rules/Devpost, product/repository/deployment, verification/evidence, video production, and final submission/freeze. Event-specific facts are examples rather than inherited requirements.
- Safety: the skill separates repository work from identity, eligibility, rights, billing, public publication, terms acceptance, and final submission. It explicitly prevents marking owner attestations complete or inferring submit authority from earlier saves/deployments.
- Video lessons: context before evidence, causal story rather than feature tour, real prompt/tool/result/decision pairing, positive human boundaries, measured scene manifests, natural speech, content-addressed narration, rights/privacy audit, public-YouTube verification, and final-frame/listen checks are preserved generically.
- Helper: `verify_demo_video.sh` passed Bash syntax and the final Agent SNR MP4; it reports 165.52 seconds, H.264 1920×1080 at 25 fps, AAC 48 kHz stereo, −19.1 dB mean / −1.3 dB peak, zero silence at least three seconds, and SHA-256 `bf0faf8744b4ef731c306b70724f9cdfc5fe071e1bf3b8fe7c778ddf4a3dc71c`. It correctly rejects a 100-second maximum.
- Validation: the bundled skill validator passes and no scaffold placeholder remains. The repository-side handoff is `submission/reusable-hackathon-skill.md`.
