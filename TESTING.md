# Testing and release gates

## Supported matrix

| Purpose | WordPress | WooCommerce | PHP | Storage / checkout |
|---|---:|---:|---:|---|
| Minimum | 6.9 | 10.9.4 | 8.1 | Legacy orders + classic |
| Primary | 7.1 | 11.0.1 | 8.3/8.4 | HPOS + classic |
| Deadline rerun | 7.1 | 11.1 final, if available | 8.3/8.5 | HPOS + classic |
| Portability | latest | current stable | 8.3 | Playground SQLite |
| Degraded mode | 6.9+ | absent | 8.1+ | Non-commerce tools only |

WooCommerce has [tentatively delayed 11.1 final to September 3](https://developer.woocommerce.com/2026/09/01/woocommerce-11-1-delayed/). If it becomes available before the repository is tagged, run the complete suite against it. Checkout Block compatibility is not claimed for v0.1.

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

The automated suites currently contain 108 JavaScript contract/configuration tests, 106 PHP tests with 1,940 assertions, and 15 isolated Chromium scenarios. The PHP suite covers guide/schema and effect-reference contracts, privacy-safe demand signatures, full-result and out-of-stock analysis, evidence scope, metric enrichment and sample provenance, semantic and transport idempotency, database-enforced feedback slots, output budgets, capability compatibility, plus mixed class-string/object WooCommerce gateways. The JavaScript suite covers provenance, single/latest/unavailable measurement labels, safe text rendering, stale opportunity clearing, eval schema/report/smoke correctness, and fail-closed showcase configuration. The REST smoke harness covers seven public routes, 20 publicly discoverable tools plus two non-discoverable legacy compatibility abilities, guide discovery, automatic opportunities, agent feedback, bounded workflow replay, private cart snapshots, Origin/CSRF denial, replay conflicts, policy denial/recovery, analytics, and reset.

The WooCommerce lifecycle gate uses public Woo CRUD APIs and explicit provenance fixtures in the configured storage mode. It proves unpaid exclusion, paid attribution, partial and full refund recomputation, cancellation removal, provenance-negative line handling, and human-only exclusion. Every fixture is tagged and deleted before the test exits.

The Chromium suite separately exercises the complete browser path: Agent Guide discovery, automatic zero-result recording, constrained recovery, structured feedback and provenance, WebMCP search/cart/handoff, existing- and late-tab cart-badge synchronization, bounded partial replay, classic checkout, the real no-charge gateway, paid direct attribution, an actual agent removal followed by a human re-add (influenced, never direct), gateway exclusion from a human-only cart, policy refresh, and cross-session isolation.

Branch CI validates Composer metadata, WordPress Coding Standards, PHP/JavaScript tests, the plugin ZIP layout, Playground execution, Plugin Check, and the WordPress/WooCommerce matrix with both order-storage modes. The tag workflow builds once, mounts the extracted release-candidate ZIP in every matrix job, reruns the REST, Woo lifecycle, Plugin Check, and primary Chromium gates, and cannot publish until they all pass.

## WebMCP validation ladder

No single harness proves tool quality or a complete agent journey. Run these gates in order and keep each evidence class distinct:

1. **Deterministic repository checks** validate the canonical catalog, strict schemas, annotations, character/output budgets, privacy, idempotency, cart/order behavior, UI synchronization, and artifact parity.
2. **Pinned GoogleChromeLabs WebMCP Evals smoke** validates native Chrome discovery and safe concrete calls on both top-level surfaces. It is keyless and runs only against the disposable localhost demo because the pinned CLI launches Chrome without its sandbox.
3. **Protected model-backed selection and browser evals** test paraphrases, ambiguity, no-call safety, argument extraction, sequence, and result-aware recovery on one explicitly recorded model/version. Reports under `.evals/` are private evidence and require an explicit report check; a process exit alone is not proof of a passing probabilistic suite.
4. **nekuda WebMCP Workbench 1.2.1** validates the frozen release as an end user: discovered tools/schemas, manual happy/error calls, Audit findings, saved-call replay, repeated evals, approvals/logs, and User Mode. Complete [submission/workbench-validation.md](submission/workbench-validation.md); this is an owner-run gate, not current repository evidence.
5. **WebMCP.com scanner, API, and directory** validate the deployed top-level HTTPS surfaces and external discoverability. The scanner checks the live page; the read-only API confirms indexed directory state only after human listing review. Follow [submission/webmcp-directory-listing.md](submission/webmcp-directory-listing.md).

The exact fixture layout, pinned CLI version, safe commands, thresholds, and report semantics live in [evals/README.md](evals/README.md). Model keys must remain outside prompts, reports, screenshots, logs, and Git. Probabilistic and external evidence supplements deterministic tests; it never replaces them.

## Local judge path

1. Run `./bin/start-demo.sh` and open the printed judge URL.
2. Confirm the page remains useful without a WebMCP API and reports **Unsupported browser** rather than failing.
3. In a supported top-level WebMCP client, confirm the status progresses from **API detected** to **Tools ready** only after registration succeeds.
4. Run the shopper prompt from `README.md`. Verify visible search results, comparison evidence, policy evidence, cart badge/summary, and workflow rail.
5. Confirm the agent reads the visible Agent Guide, then search for an in-stock waterproof backpack under $100 with IPX5. Verify zero results create a server-confirmed **Site observed** opportunity without a feedback call.
6. Relax only the water rating. Confirm exactly RainTrail and HarborLite match, both IPX4; compare them, verify returns, add HarborLite, and prepare checkout.
7. Follow the guide's feedback instructions. Confirm only structured enums/evidence IDs are submitted, the receipt is **Agent reported**, linked site metrics are separate, and conversion/value remain pending before the order.
8. Confirm checkout handoff does not create an order or navigate automatically. Click the human checkout CTA, review the prefilled fictional demo fields, choose **Demo payment — no charge**, and place the order.
9. Open Agent SNR and run the merchant prompt. Confirm the journey, Workflow Replay, Opportunity Signals, tool health, observed demand, agent feedback, verified paid order, attribution class, gross revenue, refunds, and net revenue refer only to this demo session.
10. Ask to disable comparison for this demo session. Confirm the server blocks it immediately and the local/cross-tab manifest refresh removes it without affecting a second isolated browser profile.
11. Reset the current demo. Confirm a fresh scope appears and another browser profile remains unchanged.

## Real-client release checks

Record exact app/browser versions and results here before tagging:

| Client | Required setup | Result |
|---|---|---|
| ChatGPT desktop | Latest app; Work or Codex workspace; Site Tools permission; GPT-5.6 Sol or Terra | PENDING OWNER TEST |
| Chrome | 149+ with `chrome://flags/#enable-webmcp-testing`; top-level HTTPS/localhost | PENDING OWNER TEST |
| nekuda WebMCP Workbench | Version 1.2.1 baseline; dedicated Chrome profile; frozen top-level HTTPS pages | PENDING OWNER TEST |
| WebMCP.com scanner/API | Frozen public URLs; human directory review for post-index API verification | PENDING OWNER TEST |
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
- automatic opportunity rows exclude raw search text and unknown attributes;
- feedback rejects caller metric values, free-form comments, foreign or non-storefront evidence, and a third unique report in one workflow;
- site-observed, agent-reported, and site-verified provenance remain separate in API responses and rendered text;
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
