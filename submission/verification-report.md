# Repository verification report

Prepared locally on August 30, 2026. This report distinguishes reproducible repository evidence from actions that require the entrant's accounts, identity, infrastructure, or final naming decision.

## Reproducible artifacts

Two consecutive clean builds produced identical bytes:

| Artifact | SHA-256 |
|---|---|
| `wmcp-agentops-0.1.0.zip` | `1891a27aa823fcecdf13844978eddb16dc647191e80b4f31d475dba75f555174` |
| `wmcp-agentops-playground-0.1.0.zip` | `5fdcb23b3ba23c4c76d080f8d1e24331822b2d31f7327fbf56be9c6256a822e6` |

The final Playground bundle executed successfully with `@wp-playground/cli` 3.1.51. The final plugin ZIP was re-extracted into clean matrix environments; REST, Woo lifecycle, 12-scenario Chromium, and Plugin Check acceptance passed against that exact artifact.

## Green automated gates

| Gate | Result |
|---|---|
| PHP syntax | All plugin and test PHP files pass |
| PHPUnit, PHP 8.1 | 58 tests, 521 assertions |
| PHPUnit, PHP 8.4 | 58 tests, 521 assertions |
| WordPress Coding Standards | Zero errors; line-length warnings only |
| JavaScript | 42 tests; ESLint passes |
| CSS | Stylelint passes |
| Dependency audit | Zero npm vulnerabilities |
| REST/security smoke | Passes seven routes, 19 tools, shopper, analytics, governance, and reset |
| Chromium acceptance | 12/12 pass serially, including two real classic-checkout orders |
| Woo lifecycle | Unpaid, paid, partial/full refund, cancel, provenance-negative, and human-only cases pass |
| Plugin Check 2.1.0 | Zero errors; 56 documented warnings |
| Repository hygiene | Workflow YAML parses, shell syntax passes, and `git diff --check` is clean |

## Exact plugin ZIP matrix

The same built `wmcp-agentops-0.1.0.zip` was extracted and mounted read-only in isolated clean environments.

| Environment | Order storage | Result |
|---|---|---|
| WordPress 6.9 / PHP 8.1.34 / WooCommerce 10.9.4 | Legacy | Activation, seed, REST smoke, and Woo lifecycle pass |
| WordPress 7.0.4 / PHP 8.3.33 / WooCommerce 11.0.1 | Legacy | Activation, seed, REST smoke, and Woo lifecycle pass |
| WordPress 7.1 / PHP 8.3 / WooCommerce 11.0.1 | HPOS | REST smoke, Woo lifecycle, 12 Chromium scenarios, and Plugin Check pass |

The tag workflow runs that real Woo CRUD lifecycle in all three exact-artifact matrix targets.

## Plugin Check warnings

Plugin Check reports no errors. The warnings fall into expected, reviewable categories:

- namespaced/prefixed identifiers flagged by conservative global-prefix checks;
- direct database queries and cache/schema operations used for the plugin-owned analytics ledger;
- the working name's restricted “WP” prefix, which cannot be resolved until the human entrant selects the final public name;
- the included `THIRD_PARTY_NOTICES.md` file.

These warnings do not hide executable errors. If the plugin is later submitted to the WordPress.org directory, resolve the final name/slug first and re-review every remaining warning against that directory's requirements.

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

- Select and propagate the final public name/slug after a human name and trademark check.
- Confirm the Git author identity, create the public remote, and push the unsquashed history.
- Deploy the release tag to stable top-level HTTPS WordPress hosting.
- Validate the frozen public site in real ChatGPT desktop Site Tools and flag-enabled Chrome.
- Re-run the complete matrix against final WooCommerce 11.1 if it releases before tagging.
- Capture original screenshots, record/publish the sub-three-minute YouTube video, replace URL placeholders, submit Devpost, and freeze all judged resources.

The operational sequence and exact checkboxes are in [owner-actions.md](owner-actions.md) and [final-checklist.md](final-checklist.md).
