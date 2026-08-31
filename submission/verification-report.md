# Repository verification report

Prepared locally on August 30, 2026. This report distinguishes reproducible repository evidence from actions that require the entrant's accounts, identity, infrastructure, or formal trademark/domain clearance.

## Reproducible artifacts

Two consecutive clean builds produced identical bytes:

| Artifact | SHA-256 |
|---|---|
| `wmcp-agentops-0.1.0.zip` | `6bc90a59837a46fbb350dda53cb3b503e60e11315f4a2c804f163d0888e27c89` |
| `wmcp-agentops-playground-0.1.0.zip` | `96bbd8ef4d09f392d8d9d0ebdddba30e34f0d232826bb9688f6243609cf3d978` |

The final Playground bundle executed successfully with `@wp-playground/cli` 3.1.51. The final plugin ZIP was re-extracted into clean matrix environments; REST, Woo lifecycle, 14-scenario Chromium, and Plugin Check acceptance passed against that exact artifact.

## Green automated gates

| Gate | Result |
|---|---|
| PHP syntax | All plugin and test PHP files pass |
| PHPUnit, PHP 8.1 | 68 tests, 606 assertions |
| PHPUnit, PHP 8.4 | 68 tests, 606 assertions |
| WordPress Coding Standards | Zero errors; line-length warnings only |
| JavaScript | 79 tests, including fail-closed showcase origin/redirect/credential/output configuration; ESLint passes |
| Showcase launcher guards | Automated invalid-port, reset-confirmation, project/loopback, checksum, symlink-entry, and extracted-cache integrity/repair checks pass |
| CSS | Stylelint passes |
| Dependency audit | Zero npm vulnerabilities |
| REST/security smoke | Passes seven routes, 19 tools, shopper, analytics, governance, and reset |
| Chromium acceptance | 14/14 pass serially, including bounded large-workflow replay, cross-tab cart synchronization, and two real classic-checkout orders |
| Woo lifecycle | Unpaid, paid, partial/full refund, cancel, provenance-negative, and human-only cases pass |
| Plugin Check 2.1.0 | Zero errors; 54 documented warnings; zero trademark findings |
| Repository hygiene | Workflow YAML parses, shell syntax passes, and `git diff --check` is clean |

## Local showcase and media verification

The following artifact-level checks were performed locally in addition to `npm run verify`:

- `./bin/start-showcase.sh start` built a missing release ZIP when needed, mounted the verified artifact under the isolated `agent-snr-showcase` project on `127.0.0.1`, and verified WooCommerce 11.0.1, 12 products, and all four public pages.
- The exact mounted ZIP loaded WooCommerce PayPal Payments 4.1.2 object-based gateways without an Agent SNR fatal and completed the full no-charge order/refund capture with zero console errors; the live development admin dashboard also recovered with PayPal Payments and WooPayments still active.
- `npm run showcase:capture` completed one eight-call TerraRoll capability-gap → human order → full-refund workflow with zero console errors. The machine-readable evidence is in `demo-screenshots/showcase-summary.json`.
- `agent-snr-hackathon-demo.pptx` rendered as 11 editable slides; all 11 speaker-note source blocks were present and the presentation overflow check passed.

The runtime capture and presentation render are recorded local artifact checks, not hidden steps inside `npm run verify`. The hosted HTTPS capture and real-client checks remain owner gates.

## Exact plugin ZIP matrix

The same built `wmcp-agentops-0.1.0.zip` was extracted and mounted read-only in isolated clean environments.

| Environment | Order storage | Result |
|---|---|---|
| WordPress 6.9 / PHP 8.1.34 / WooCommerce 10.9.4 | Legacy | Activation, seed, REST smoke, and Woo lifecycle pass |
| WordPress 7.0.4 / PHP 8.3.33 / WooCommerce 11.0.1 | Legacy | Activation, seed, REST smoke, and Woo lifecycle pass |
| WordPress 7.1 / PHP 8.3 / WooCommerce 11.0.1 | HPOS | REST smoke, Woo lifecycle, 14 Chromium scenarios, and Plugin Check pass |

The tag workflow runs that real Woo CRUD lifecycle in all three exact-artifact matrix targets.

## Plugin Check warnings

Plugin Check reports no errors. The warnings fall into expected, reviewable categories:

- namespaced/template identifiers flagged by conservative global-prefix checks;
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
