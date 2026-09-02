# Repository verification report

Prepared locally on September 1–2, 2026. This report distinguishes reproducible repository evidence from actions that require the entrant's accounts, identity, infrastructure, browser-extension installation, or formal trademark/domain clearance.

## Reproducible artifacts

Two consecutive clean builds produced identical bytes:

| Artifact | SHA-256 |
|---|---|
| `wmcp-agentops-0.1.0.zip` | `38b1b8106f255b051ff07c953afb6db3be4504df85ac1c4f454c69ec82a416fa` |
| `wmcp-agentops-playground-0.1.0.zip` | `96a9ec44596a76a07e5d549ff486d35acd8c0292cd5878db6e361321664d3f89` |

The final Playground bundle executed successfully with `@wp-playground/cli` 3.1.51. The final plugin ZIP was re-extracted into clean matrix environments; REST, Woo lifecycle, 16-scenario Chromium, and Plugin Check acceptance passed against that exact artifact.

## Green automated gates

| Gate | Result |
|---|---|
| PHP syntax | All plugin and test PHP files pass |
| PHPUnit, PHP 8.1 | 111 tests, 1,964 assertions |
| PHPUnit, PHP 8.4 | 111 tests, 1,964 assertions |
| WordPress Coding Standards | Zero errors; line-length warnings only |
| JavaScript | 112 tests, including provenance/sample labeling, safe rendering, stale-opportunity clearing, WebMCP eval fixture/provenance enforcement, application-error-aware smoke, and fail-closed showcase configuration; ESLint passes |
| Showcase launcher guards | Automated invalid-port, reset-confirmation, project/loopback, checksum, symlink-entry, and extracted-cache integrity/repair checks pass |
| CSS | Stylelint passes |
| Dependency audit | Zero npm vulnerabilities |
| REST/security smoke | Passes seven routes, 20 publicly discovered tools, two hidden legacy compatibility abilities, Guide 1.1, observed opportunities, linked feedback, analytics, governance, and reset |
| Native WebMCP smoke | Pinned `webmcp-evals@0.0.4` discovers and invokes 5/5 storefront plus 6/6 Agent SNR read calls in Chrome; the repository adapter rejects application `{ "ok": false }` envelopes |
| WebMCP eval guards | 21/21 fixture, patch, checker, and smoke tests pass |
| Chromium acceptance | 16/16 pass serially, including fresh-session feedback correlation, guide/feedback provenance, bounded replay, cross-tab cart synchronization, and two real classic-checkout orders |
| Woo lifecycle | Unpaid, paid, partial/full refund, cancel, provenance-negative, and human-only cases pass |
| Plugin Check 2.1.0 | Zero errors; 73 documented warnings; zero trademark findings |
| Repository hygiene | Workflow YAML parses, shell syntax passes, and `git diff --check` is clean |

## Protected model-backed eval status

The remediated protected selection and live-browser evals pass the strict all-pass gate at commit `e4d9c86b2754c735094b1dc8437fbd007d3e557a`: storefront selection passes 54/54 case-runs, Agent SNR selection passes 45/45, and the live shopper journey passes all 8/8 required rows with zero console errors and no new WooCommerce order. All three provenance checkers pass against the recorded fixed model, exact artifacts, schemas, fixtures, run counts, and execution policy. Complete before/after evidence and private-report hashes are in [`webmcp-eval-report-2026-09-01.md`](webmcp-eval-report-2026-09-01.md).

The initial 33/54 storefront, 31/45 Agent SNR, and 3/8 browser result is retained in that report and Git history as the superseded baseline that drove the remediation; it is no longer an active release blocker.

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
| WordPress 7.1 / PHP 8.3 / WooCommerce 11.0.1 | HPOS | REST smoke, Woo lifecycle, 16 Chromium scenarios, and Plugin Check pass |

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
- Complete the Workbench evidence sheet and WebMCP.com scanner/API/human listing handoff against the frozen HTTPS release.
- Re-run the complete matrix against final WooCommerce 11.1 if it releases before tagging.
- Capture original screenshots, record/publish the sub-three-minute YouTube video, replace URL placeholders, submit Devpost, and freeze all judged resources.

The operational sequence and exact checkboxes are in [owner-actions.md](owner-actions.md) and [final-checklist.md](final-checklist.md).
