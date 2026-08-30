# Testing and release gates

## Supported matrix

| Purpose | WordPress | WooCommerce | PHP | Storage / checkout |
|---|---:|---:|---:|---|
| Minimum | 6.9.4 | 10.9.4 | 8.1 | Legacy orders + classic |
| Primary | 7.1 | 11.0.1 | 8.3/8.4 | HPOS + classic |
| Deadline rerun | 7.1 | 11.1 final | 8.3/8.5 | HPOS + classic |
| Portability | latest | current stable | 8.3 | Playground SQLite |
| Degraded mode | 6.9+ | absent | 8.1+ | Non-commerce tools only |

WooCommerce 11.1 is scheduled before the submission deadline. Its final release must receive the complete suite before the repository is tagged. Checkout Block compatibility is not claimed for v0.1.

## Automated checks

```bash
npm ci
npm run verify
```

Individual checks:

```bash
npm run lint:js
npm run lint:css
npm run test:showcase
npm run test:js
npm run test:browser
./bin/lint-php.sh
./bin/run-smoke-tests.sh
./bin/run-woo-lifecycle-tests.sh
./bin/build-plugin.sh
./bin/build-playground-bundle.sh
```

Run the complete host-independent static/unit/build slice with `./bin/verify.sh`. The REST, Woo lifecycle, and browser checks require the local demo started by `./bin/start-demo.sh`.

The automated suites currently contain 79 JavaScript contract/configuration tests, 65 PHP tests with 597 assertions, and 14 isolated Chromium scenarios. The JavaScript suite includes fail-closed showcase origin, redirect, credential, console-location, and output-directory checks. The REST smoke harness covers seven public routes, 19 tools, bounded workflow replay, private cart-count manifest snapshots, session bootstrap/fixation, Origin and CSRF denial, stale cart revisions, request-ID conflicts, policy denial/recovery, analytics, and reset.

The WooCommerce lifecycle gate uses public Woo CRUD APIs and explicit provenance fixtures in the configured storage mode. It proves unpaid exclusion, paid attribution, partial and full refund recomputation, cancellation removal, provenance-negative line handling, and human-only exclusion. Every fixture is tagged and deleted before the test exits.

The Chromium suite separately exercises the complete browser path: WebMCP search/cart/handoff, existing- and late-tab cart-badge synchronization, bounded partial replay for a large Agent Sessions workflow, normal classic checkout, the real no-charge demo gateway, paid direct attribution, an actual agent removal followed by a normal human re-add (influenced, never direct), and withholding the gateway from a human-only cart.

Branch CI validates Composer metadata, WordPress Coding Standards, PHP/JavaScript tests, the plugin ZIP layout, Playground execution, Plugin Check, and the WordPress/WooCommerce matrix with both order-storage modes. The tag workflow builds once, mounts the extracted release-candidate ZIP in every matrix job, reruns the REST, Woo lifecycle, Plugin Check, and primary Chromium gates, and cannot publish until they all pass.

## Local judge path

1. Run `./bin/start-demo.sh` and open the printed judge URL.
2. Confirm the page remains useful without a WebMCP API and reports **Unsupported browser** rather than failing.
3. In a supported top-level WebMCP client, confirm the status progresses from **API detected** to **Tools ready** only after registration succeeds.
4. Run the shopper prompt from `README.md`. Verify visible search results, comparison evidence, policy evidence, cart badge/summary, and workflow rail.
5. Ask for a blue TerraRoll 25 Pack back-in-stock notification. Confirm the out-of-stock product is recorded as the related capability-gap evidence and the agent explicitly says no notification was created.
6. Run checkout handoff. Confirm it does not create an order or navigate automatically.
7. Click the human checkout CTA, review the prefilled fictional demo fields, choose **Demo payment — no charge**, and place the order.
8. Open Agent SNR and run the merchant prompt. Confirm the journey, Workflow Replay, Signals, tool health, capability gap, paid order, attribution class, gross revenue, refunds, and net revenue refer only to this demo session.
9. Ask to disable comparison for this demo session. Confirm the server blocks it immediately and the local/cross-tab manifest refresh removes it without affecting a second isolated browser profile.
10. Reset the current demo. Confirm a fresh scope appears and another browser profile remains unchanged.

## Real-client release checks

Record exact app/browser versions and results here before tagging:

| Client | Required setup | Result |
|---|---|---|
| ChatGPT desktop | Latest app; Work or Codex workspace; Site Tools permission; GPT-5.6 Sol or Terra | PENDING OWNER TEST |
| Chrome | 149+ with `chrome://flags/#enable-webmcp-testing`; top-level HTTPS/localhost | PENDING OWNER TEST |
| Normal browser | WebMCP absent | Automated fallback and cache-neutral HTML pass; hosted manual check pending |

ChatGPT currently does not discover tools in any iframe and does not expose declarative form tools as Site Tools. Playground therefore verifies portability and admin/human behavior, not the primary in-app-browser discovery path.

Client `AbortSignal` cancellation stops the browser fetch and UI wait. PHP/WooCommerce work that already began may still finish; the workflow ledger records that authoritative server outcome rather than falsely labeling a committed write cancelled.

## Security cases

- unknown fields/types, oversize strings/arrays, and malformed envelopes fail before callbacks;
- cross-origin, `Origin: null`, missing/expired/tampered CSRF, wrong session/workflow, and replay attempts fail closed;
- one start plus one terminal event exists per authenticated/resolved request ID;
- state-changing replay never duplicates a cart, policy, or order effect;
- another demo-session workflow cannot be queried or mutated;
- anonymous/subscriber access cannot reach site-wide analytics or persistent policy;
- kill switch immediately blocks server execution even with stale browser tools;
- catalog/policy HTML and scripts are sanitized and never inserted as HTML;
- redaction/allowlist unit cases reject raw prompt, PII, cookie, token, nonce, authorization, and payment fields;
- session and manifest responses contain `private, no-store`; cached HTML contains no session identity;
- the demo gateway is absent when the server-side demo constant is false.

## Artifact checks

1. Build `dist/wmcp-agentops-0.1.0.zip` from a clean tag.
2. Verify its SHA-256 file and confirm the ZIP has one `wmcp-agentops/` root.
3. Scan for `.env`, Git metadata, tests, logs, caches, nested ZIPs, secrets, and development dependencies.
4. Install and activate that exact ZIP on every matrix target.
5. Run official WordPress Plugin Check against the extracted ZIP.
6. Rebuild the artifact from the tag and document provenance.
7. Deploy the same tag, run smoke tests against public logged-out URLs, then freeze repository/site/Devpost through judging.

The current local verification record, including exact matrix versions, checksums, and remaining owner-only gates, is in [submission/verification-report.md](submission/verification-report.md).
