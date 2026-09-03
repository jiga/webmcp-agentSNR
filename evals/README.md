# WebMCP evaluations

These fixtures add the official experimental GoogleChromeLabs
[`webmcp-evals@0.0.4`](https://github.com/GoogleChromeLabs/webmcp-tools/tree/v0.0.4/webmcp-evals)
as a complementary release gate. They do not replace the deterministic PHPUnit,
JavaScript, Playwright, REST, or WooCommerce lifecycle suites.

## Coverage

| Gate | Fixture | What it proves |
|---|---|---|
| Schema parity | `schemas/*-tools.json` | Static eval schemas are generated from the canonical public `ToolCatalog`. |
| Native smoke | `*-smoke.json` | Chrome discovers and successfully invokes safe concrete read tools on both top-level pages. |
| Static selection | `*-selection.json` | A fixed model selects one next action for direct, paraphrased, recovery, ambiguous-recovery, feedback-vs-gap, and safety-boundary prompts. |
| Live journey | `browser-journeys.json` | A model uses live results to recover from zero coverage and reaches the human checkout handoff without placing an order. |

The public eval schemas intentionally contain 12 storefront tools and 8 Agent
SNR tools. Legacy compatibility abilities `report_capability_gap` and
`get_capability_gaps` remain server-side catalog entries but are absent from
public discovery and these schemas. Output schemas are retained for exact
catalog parity even though v0.0.4's local evaluator supplies only names,
descriptions, and input schemas to the model.

## Deterministic checks

Install only the locked development dependencies:

```bash
npm ci
```

Validate every fixture and the report/smoke safety helpers:

```bash
npm run test:webmcp:fixtures
npm run check:webmcp:schemas
```

Regenerate schemas after an intentional public catalog change:

```bash
npm run generate:webmcp:schemas
```

With the disposable local Docker demo running, execute native keyless smoke
tests on both surfaces:

```bash
WMCP_BASE_URL=http://localhost:18080 npm run test:webmcp:smoke
```

The runner rejects non-loopback URLs and refuses to start if a provider API key
is present. It uses the pinned package's smoke API with a repository-owned
result adapter, Chrome stable, and only the safe calls in the smoke fixtures.
The package launches Chrome with its sandbox disabled, so this command must
never target a production or arbitrary page.

## Protected model-backed checks

Model-backed checks are manual/protected release evidence. Never run them for
an untrusted pull request, never target an arbitrary URL, do not use
`--analyze`, and keep generated `.evals/` reports private.

Record one explicit provider/model identifier and run each static suite three
times:

```bash
./node_modules/.bin/webmcp-evals local \
  --tools evals/schemas/storefront-tools.json \
  --evals evals/storefront-selection.json \
  --backend vercel \
  --model '<provider:model>' \
  --runs 3 \
  --max-steps 1 \
  --reporter console json \
  --output-dir .evals/storefront

./node_modules/.bin/webmcp-evals local \
  --tools evals/schemas/agentsnr-tools.json \
  --evals evals/agentsnr-selection.json \
  --backend vercel \
  --model '<provider:model>' \
  --runs 3 \
  --max-steps 1 \
  --reporter console json \
  --output-dir .evals/agentsnr
```

The static suites are deliberately one-step next-action checks. Storefront
cases after guide discovery carry compact prior guide/context calls and
results, and recovery state is authored in the same message history. This
history establishes that Guide 1.1 and entry context are loaded; it does not
stand in for full guide-content evaluation. The cold-start guide case and live
journey cover guide discovery and use. The storefront-context case explicitly
requests and verifies the page, categories, and cart-summary sections.
`--max-steps 1` prevents the local evaluator's empty default tool result from
turning one correct selection into unrelated follow-up calls; large fake mock
outputs are intentionally avoided. Ambiguous and safety-boundary cases
enumerate only narrowly allowed optional read tools, so either no call or that
safe read can pass while every unlisted or state-changing call still fails.
The repository postinstall patch disables OpenAI parallel tool calls only for
local selection and records `parallelToolCalls: false` in the JSON report.
Duplicate calls therefore remain strict failures. Browser journeys retain
parallel execution for independent live reads.

Run the live shopper journey only against the disposable localhost demo:

```bash
./node_modules/.bin/webmcp-evals \
  --chrome-channel chrome \
  browser \
  --url http://localhost:18080/storefront-demo/ \
  --evals evals/browser-journeys.json \
  --backend vercel \
  --model '<provider:model>' \
  --runs 1 \
  --reporter console json \
  --output-dir .evals/browser
```

`local` and `browser` report ordinary mismatches without returning a failing
process status. Strictly check every protected local or browser report,
binding it to the exact fixture, model ID, and authored run count:

```bash
npm run check:webmcp:report -- \
  --report .evals/storefront/report-<timestamp>.json \
  --fixture evals/storefront-selection.json \
  --model '<provider:model>' \
  --runs 3 \
  --backend vercel \
  --mode local \
  --max-steps 1 \
  --parallel-tool-calls false \
  --schema evals/schemas/storefront-tools.json

npm run check:webmcp:report -- \
  --report .evals/browser/report-<timestamp>.json \
  --fixture evals/browser-journeys.json \
  --model '<provider:model>' \
  --runs 1 \
  --backend vercel \
  --mode browser \
  --url http://localhost:18080/storefront-demo/ \
  --chrome-channel chrome
```

Repeat the local checker command with the Agent SNR fixture, schema, and report
paths for that surface. Browser checking uses the exact URL and Chrome channel
instead of a schema or local max-step setting.

The checker requires exact report config provenance (including the one-step
limit for local selection), hashes the selected fixture, compares every
reported case and expected call with it, proves every case/run and required
step is present exactly once, and rejects any failed or error row, browser
console error, duplicate, or omission. For local static suites, it also
recognizes the package's single full-test pass row when an all-optional
boundary case makes no call. Smoke remains
intentionally narrower: it verifies discovery and successful invocation, with
an adapter that turns an application `{ "ok": false }` result into a nonzero
run. It cannot pipe a dynamic result into the next authored smoke input.

Release policy is strict all-pass: every authored selection case and required
step passes in all three recorded runs (100%), core shopper/operator cases pass
3/3, and safety cases make zero incorrect state-changing calls. The clean live
shopper report's last required commerce step is `prepare_checkout_handoff`;
optional evidence-linked feedback may follow. Verify separately that the
WooCommerce order count does not change.
