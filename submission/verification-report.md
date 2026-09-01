# Repository verification report

Prepared locally on August 31, 2026. This report distinguishes reproducible repository evidence from actions that require the entrant's accounts, identity, infrastructure, or formal trademark/domain clearance.

## Reproducible artifacts

Two consecutive clean builds produced identical bytes:

| Artifact | SHA-256 |
|---|---|
| `wmcp-agentops-0.1.0.zip` | `f631ff06279ba11cc153789e960c4a3641be3dee81d1f81b2681b415ed45b940` |
| `wmcp-agentops-playground-0.1.0.zip` | `1a47231b071cd84915d4e7a5faf8a051656e630aeedd5ddfe6d0af9082f5348d` |

The final Playground bundle executed successfully with `@wp-playground/cli` 3.1.51. The final plugin ZIP was re-extracted into clean matrix environments; REST, Woo lifecycle, 15-scenario Chromium, and Plugin Check acceptance passed against that exact artifact.

## Green automated gates

| Gate | Result |
|---|---|
| PHP syntax | All plugin and test PHP files pass |
| PHPUnit, PHP 8.1 | 102 tests, 858 assertions |
| PHPUnit, PHP 8.4 | 102 tests, 858 assertions |
| WordPress Coding Standards | Zero errors; line-length warnings only |
| JavaScript | 91 tests, including provenance/sample labeling, safe rendering, stale-opportunity clearing, and fail-closed showcase configuration; ESLint passes |
| Showcase launcher guards | Automated invalid-port, reset-confirmation, project/loopback, checksum, symlink-entry, and extracted-cache integrity/repair checks pass |
| CSS | Stylelint passes |
| Dependency audit | Zero npm vulnerabilities |
| REST/security smoke | Passes seven routes and all 22 tools, including guide, observed opportunities, linked feedback, analytics, governance, and reset |
| Chromium acceptance | 15/15 pass serially, including guide/feedback provenance, bounded replay, cross-tab cart synchronization, and two real classic-checkout orders |
| Woo lifecycle | Unpaid, paid, partial/full refund, cancel, provenance-negative, and human-only cases pass |
| Plugin Check 2.1.0 | Zero errors; 73 documented warnings; zero trademark findings |
| Repository hygiene | Workflow YAML parses, shell syntax passes, and `git diff --check` is clean |

## Local showcase and media verification

The following artifact-level checks were performed locally in addition to `npm run verify`:

- `./bin/start-showcase.sh start` built a missing release ZIP when needed, mounted the verified artifact under the isolated `agent-snr-showcase` project on `127.0.0.1`, and verified WooCommerce 11.0.1, 12 products, and all four public pages.
- Mixed class-string/object WooCommerce gateway compatibility remains covered by dedicated regression tests. The clean exact-ZIP showcase completed the full no-charge order/refund capture with zero console errors.
- `npm run showcase:capture` completed one nine-call Guide → IPX5 zero-result → IPX4 recovery → linked feedback → HarborLite human order → full-refund workflow with zero console errors. Ten 1440×900 captures and machine-readable evidence are in `demo-screenshots/`.
- `agent-snr-hackathon-demo.pptx` rendered as 11 editable slides. All 11 speaker-note source blocks, imported theme fidelity, template fidelity, package integrity, placeholder audit, and overflow checks passed; every exported slide was inspected individually.

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
6. reruns Plugin Check and Chromium acceptance on the primary HPOS target;
7. publishes only after the complete acceptance matrix succeeds.

## Intentionally open owner gates

- Complete formal trademark/domain clearance for Agent SNR and confirm the public repository/product slug.
- Confirm the Git author identity, create the public remote, and push the unsquashed history.
- Deploy the release tag to stable top-level HTTPS WordPress hosting.
- Validate the frozen public site in real ChatGPT desktop Site Tools and flag-enabled Chrome.
- Re-run the complete matrix against final WooCommerce 11.1 if it releases before tagging.
- Capture original screenshots, record/publish the sub-three-minute YouTube video, replace URL placeholders, submit Devpost, and freeze all judged resources.

The operational sequence and exact checkboxes are in [owner-actions.md](owner-actions.md) and [final-checklist.md](final-checklist.md).
