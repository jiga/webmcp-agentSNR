# Repository verification report

Prepared locally on September 1–3, 2026. This report distinguishes reproducible repository evidence from actions that require the entrant's accounts, identity, infrastructure, or formal trademark/domain clearance.

## Current Agent SNR package build

Two consecutive post-rename builds produced byte-identical packages:

| Artifact | SHA-256 |
|---|---|
| `wmcp-agentsnr-0.1.0.zip` | `514a7f86fe4fadb0d3786ded3a58017a4be0c26546f5925533bd8e1d31a58943` |
| `wmcp-agentsnr-playground-0.1.0.zip` | `fc260b06107aa040e8875ec94f8850a4c207720b75d38e326ef3b29aa80de280` |

The renamed source passes PHP syntax, 111 PHPUnit tests / 1,964 assertions, 125 JavaScript/configuration tests, JavaScript/CSS lint, Coding Standards with zero errors, schema parity, Render configuration tests, showcase-launcher guards, and package ZIP integrity. The same renamed ZIP passes REST/security smoke, 16/16 Chromium scenarios, 11/11 native WebMCP calls, legacy and HPOS Woo lifecycle, the isolated three-environment cross-version matrix, Plugin Check with zero errors, fresh screenshot capture, and presentation QA. Protected model-backed evals and hosted HTTPS checks remain to be bound to the final renamed commit before publication.

## Retired exact engineering candidate

Two consecutive clean builds produced identical bytes:

| Artifact | SHA-256 |
|---|---|
| Retired pre-rename plugin artifact | `38b1b8106f255b051ff07c953afb6db3be4504df85ac1c4f454c69ec82a416fa` |
| Retired pre-rename Playground artifact | `96a9ec44596a76a07e5d549ff486d35acd8c0292cd5878db6e361321664d3f89` |

These hashes identify the last fully exercised engineering candidate at commit `e4d9c86b2754c735094b1dc8437fbd007d3e557a`. Its Playground bundle executed successfully with `@wp-playground/cli` 3.1.51, and its plugin ZIP was re-extracted into clean matrix environments; REST, Woo lifecycle, 16-scenario Chromium, and Plugin Check acceptance passed against that exact candidate.

They are deliberately **not** labeled final-submission hashes or checksums for the renamed `wmcp-agentsnr` packages. The entrant still needs formal Agent SNR trademark/domain clearance. Validate the exact deployed build from the frozen public commit. If publishing the optional `v0.1.0` release artifacts, rerun their exact-artifact gates before filling the optional hash fields in `devpost-rules-checklist.md`.

## Green automated gates

| Gate | Result |
|---|---|
| PHP syntax | All plugin and test PHP files pass |
| PHPUnit, PHP 8.1 | 111 tests, 1,964 assertions |
| PHPUnit, PHP 8.4 | 111 tests, 1,964 assertions |
| WordPress Coding Standards | Zero errors; line-length warnings only |
| JavaScript | 125 tests, including provenance/sample labeling, safe rendering, stale-opportunity clearing, WebMCP eval fixture/provenance enforcement, application-error-aware smoke, fail-closed showcase configuration, rendered-refund capture readiness, and 12 Render deployment guards; ESLint passes |
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
- After the complete pre-public technical rename, a clean guarded reset rebuilt and verified plugin ZIP SHA-256 `514a7f86fe4fadb0d3786ded3a58017a4be0c26546f5925533bd8e1d31a58943`. The `/agentsnr-demo/` recapture's `showcase-summary.json` has SHA-256 `899c2a8c898b4e66dc2678aac3459fa6b6fe1d6cf1451b8864b8e19292d013a1`; all ten PNGs are 1440×900, visual/OCR review found no retired-name occurrence, and the final frame visibly shows one paid order, `$69.00` refunded, and `$0.00` net attributed.
- The refund-frame readiness race is regression-covered: blank or whitespace-wrapped em-dash placeholders are rejected, the script waits for the real `orders_paid`, refund, and net metrics, and populated text is accepted.
- Representative native-smoke response envelopes measured 455–1,132 characters for nine of eleven calls. The one-start Agent Guide (4,706) and nine-stage funnel (2,157) are documented exceptions to Chrome's current 1.5K recommendation; both remain below the enforced 8 KiB ceiling and are bounded structured results.
- `agent-snr-hackathon-demo.pptx` (SHA-256 `1b0a1b95d18908c1d1f712b8ba35cca752a035f1f898cbd6470d0de214b49c1a`) rendered as 12 editable slides. All four embedded screenshots match the post-rename captures, the speaker-note source paths use `plugin/wmcp-agentsnr`, and the proof slide records 125 JavaScript tests, 111 PHP tests / 1,964 assertions, and 16/16 Chromium scenarios. All 12 source blocks, imported theme fidelity, template fidelity, package integrity, zero-placeholder audit, XML/text and raster OCR, and overflow checks passed; every exported slide was inspected individually.

The runtime capture and presentation render are recorded local artifact checks, not hidden steps inside `npm run verify`. The hosted HTTPS capture and real-client checks remain owner gates.

## Current renamed exact-ZIP matrix

The current `wmcp-agentsnr-0.1.0.zip` identified above was extracted and mounted read-only in three isolated clean environments.

| Environment | Order storage | Result |
|---|---|---|
| WordPress 6.9 / PHP 8.1 / WooCommerce 10.9.4 | Legacy | Activation, storage-mode verification, REST/security smoke, seeding, and Woo lifecycle pass |
| WordPress 7.0.4 / PHP 8.3 / WooCommerce 11.0.1 | Legacy | Activation, storage-mode verification, REST/security smoke, seeding, and Woo lifecycle pass |
| WordPress 7.1 / PHP 8.3 / WooCommerce 11.0.1 | HPOS | Activation, storage-mode verification, REST/security smoke, seeding, and Woo lifecycle pass |

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
- Complete entrant/team ownership and third-party-rights declarations; the unresolved plugin contributor placeholder has been removed without inventing an ID.
- Complete formal Agent SNR trademark/domain clearance before public capture; the current tree uses the canonical `webmcp-agentSNR` repository slug and `wmcp-agentsnr` plugin slug.
- Deploy the exact build from the frozen public commit to stable top-level HTTPS WordPress hosting.
- Validate both frozen top-level pages through at least one official judge path: the latest ChatGPT desktop in-app browser or Chrome 149+ with WebMCP enabled.
- Optionally complete the Workbench evidence sheet and WebMCP.com scanner/API/human listing handoff against the frozen HTTPS release.
- Optionally rerun the matrix against WooCommerce 11.1 if a stable build exists and time permits before final deployment; the declared 10.9.4/11.0.1 range already passes.
- Capture original screenshots, record/publish the sub-three-minute YouTube video, replace URL placeholders, submit Devpost, and freeze all judged resources.

The official-rule mapping and pre-submission release record are in [devpost-rules-checklist.md](devpost-rules-checklist.md); operational steps are in [owner-actions.md](owner-actions.md) and [final-checklist.md](final-checklist.md).
