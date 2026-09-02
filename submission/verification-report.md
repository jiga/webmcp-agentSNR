# Repository verification report

Prepared locally on September 1, 2026. This report distinguishes reproducible repository evidence from actions that require the entrant's accounts, identity, infrastructure, model-provider approval, browser-extension installation, or formal trademark/domain clearance.

## Reproducible artifacts

Two consecutive clean builds produced identical bytes:

| Artifact | SHA-256 |
|---|---|
| `wmcp-agentops-0.1.0.zip` | `7cd4e74ca39c3a9dd4729a0deca3916585c87e2330cb6a65db4194d912a8ba5a` |
| `wmcp-agentops-playground-0.1.0.zip` | `3b5189dc558dddb6d87d323543e8bde02a6eab36240886874d4794dce76d398d` |

The final Playground bundle executed successfully with `@wp-playground/cli` 3.1.51. The final plugin ZIP was re-extracted into clean matrix environments; REST, Woo lifecycle, 15-scenario Chromium, and Plugin Check acceptance passed against that exact artifact.

## Green automated gates

| Gate | Result |
|---|---|
| PHP syntax | All plugin and test PHP files pass |
| PHPUnit, PHP 8.1 | 106 tests, 1,940 assertions |
| PHPUnit, PHP 8.4 | 106 tests, 1,940 assertions |
| WordPress Coding Standards | Zero errors; line-length warnings only |
| JavaScript | 108 tests, including provenance/sample labeling, safe rendering, stale-opportunity clearing, WebMCP eval fixture/provenance enforcement, application-error-aware smoke, and fail-closed showcase configuration; ESLint passes |
| Showcase launcher guards | Automated invalid-port, reset-confirmation, project/loopback, checksum, symlink-entry, and extracted-cache integrity/repair checks pass |
| CSS | Stylelint passes |
| Dependency audit | Zero npm vulnerabilities |
| REST/security smoke | Passes seven routes, 20 publicly discovered tools, two hidden legacy compatibility abilities, Guide 1.1, observed opportunities, linked feedback, analytics, governance, and reset |
| Native WebMCP smoke | Pinned `webmcp-evals@0.0.4` discovers and invokes 5/5 storefront plus 6/6 Agent SNR read calls in Chrome; the repository adapter rejects application `{ "ok": false }` envelopes |
| Chromium acceptance | 15/15 pass serially, including guide/feedback provenance, bounded replay, cross-tab cart synchronization, and two real classic-checkout orders |
| Woo lifecycle | Unpaid, paid, partial/full refund, cancel, provenance-negative, and human-only cases pass |
| Plugin Check 2.1.0 | Zero errors; 73 documented warnings; zero trademark findings |
| Repository hygiene | Workflow YAML parses, shell syntax passes, and `git diff --check` is clean |

## Protected model-backed eval status

The protected selection and live-browser evals were executed on September 1, 2026 with the recorded fixed model, exact artifact, schemas, fixtures, and run counts. The strict all-pass gate **failed**: storefront selection passed 33/54 case-runs, Agent SNR selection passed 31/45, and the live shopper journey passed 0/1 with five failed required rows. The complete provenance, safety evidence, findings, and private-report hashes are in [`webmcp-eval-report-2026-09-01.md`](webmcp-eval-report-2026-09-01.md).

This is an active release blocker, not an unrun owner gate. Fix the documented start-state, mock-output, safe no-call, journey-ordering, and feedback-path issues, then rerun the same protected suites until every authored required row passes and browser console errors are zero.

## Local showcase and media verification

The following artifact-level checks were performed locally in addition to `npm run verify`:

- `./bin/start-showcase.sh start` built a missing release ZIP when needed, mounted the verified artifact under the isolated `agent-snr-showcase` project on `127.0.0.1`, and verified WooCommerce 11.0.1, 12 products, and all four public pages.
- Mixed class-string/object WooCommerce gateway compatibility remains covered by dedicated regression tests. The clean exact-ZIP showcase completed the full no-charge order/refund capture with zero console errors.
- `npm run showcase:capture` completed one nine-call Guide → IPX5 zero-result → IPX4 recovery → linked feedback → HarborLite human order → full-refund workflow with zero console errors. Ten 1440×900 captures and machine-readable evidence are in `demo-screenshots/`.
- Representative native-smoke response envelopes measured 455–1,132 characters for nine of eleven calls. The one-start Agent Guide (4,706) and nine-stage funnel (2,157) are documented exceptions to Chrome's current 1.5K recommendation; both remain below the enforced 8 KiB ceiling and are bounded structured results.
- `agent-snr-hackathon-demo.pptx` rendered as 12 editable slides. All 12 speaker-note source blocks, imported theme fidelity, template fidelity, package integrity, zero-placeholder audit, and overflow checks passed; every exported slide was inspected individually.

The runtime capture and presentation render are recorded local artifact checks, not hidden steps inside `npm run verify`. The hosted HTTPS capture and real-client checks remain owner gates.

## Exact plugin ZIP matrix

The same built `wmcp-agentops-0.1.0.zip` was extracted and mounted read-only in isolated clean environments.

| Environment | Order storage | Result |
|---|---|---|
| WordPress 6.9 / PHP 8.1.34 / WooCommerce 10.9.4 | Legacy | Activation, seed, REST smoke, and Woo lifecycle pass |
| WordPress 7.0.4 / PHP 8.3.33 / WooCommerce 11.0.1 | Legacy | Activation, seed, REST smoke, and Woo lifecycle pass |
| WordPress 7.1 / PHP 8.3 / WooCommerce 11.0.1 | HPOS | REST smoke, Woo lifecycle, 15 Chromium scenarios, and Plugin Check pass |

The tag workflow runs that real Woo CRUD lifecycle in all three exact-artifact matrix targets.

## Plugin Check warnings

Plugin Check reports no errors. Its 73 warnings fall into expected, reviewable categories:

- template-local identifiers flagged by conservative global-prefix checks;
- direct database queries and cache/schema operations used for the plugin-owned analytics ledger;
- the included `THIRD_PARTY_NOTICES.md` file.

These warnings do not hide executable errors. Plugin Check reports no trademark finding for the tested artifact, but that automated result is not legal clearance. Before a public launch or later WordPress.org submission, complete formal trademark/domain clearance for Agent SNR and re-review every remaining warning and the final slug against the directory's requirements.

## Release enforcement

Pushes and pull requests run the static, unit, Playground, source-matrix, lifecycle, Plugin Check, and primary browser gates. A `v*` tag also starts a separate release workflow that:

1. verifies the tag matches the plugin version;
2. runs JavaScript/PHP checks and builds one checksummed candidate;
3. executes the packaged Playground bundle;
4. downloads and extracts that exact plugin ZIP into all three WordPress/Woo matrix jobs;
5. reruns REST/security and Woo lifecycle acceptance in every job;
6. reruns the pinned native WebMCP smoke, Plugin Check, and Chromium acceptance on the primary HPOS target;
7. publishes only after the complete acceptance matrix succeeds.

## Intentionally open owner gates

- Complete formal trademark/domain clearance for Agent SNR and confirm the public repository/product slug.
- Confirm the Git author identity, create the public remote, and push the unsquashed history.
- Deploy the release tag to stable top-level HTTPS WordPress hosting.
- Validate the frozen public site in real ChatGPT desktop Site Tools and flag-enabled Chrome.
- Fix and rerun the failed protected model-backed selection/browser evals documented in [`webmcp-eval-report-2026-09-01.md`](webmcp-eval-report-2026-09-01.md); then complete the Workbench evidence sheet and WebMCP.com scanner/API/human listing handoff against the frozen HTTPS release.
- Re-run the complete matrix against final WooCommerce 11.1 if it releases before tagging.
- Capture original screenshots, record/publish the sub-three-minute YouTube video, replace URL placeholders, submit Devpost, and freeze all judged resources.

The operational sequence and exact checkboxes are in [owner-actions.md](owner-actions.md) and [final-checklist.md](final-checklist.md).
