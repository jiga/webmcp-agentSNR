# Live WebMCP evaluation report — September 1–3, 2026

Status: **PASS — renamed release clears the strict model-backed gate**

> **Current evidence:** the final protected rerun is bound to pre-public Agent SNR commit `410c198963ec649ed58e21fce7c80103db3d0ad8`, the renamed release artifacts, and the hashes below. Earlier results remain in this report and Git history as transparent remediation evidence.

The initial protected run exposed real harness, search, session-correlation, and journey-design defects. A first rename-bound rerun then exposed contradictory authored cart state and an underspecified ambiguous pronoun. Both rounds were fixed without allowing duplicate or state-changing calls to bypass the gate. The same fixed model now passes every authored storefront, Agent SNR, and live-browser requirement against the renamed package.

## Before and after

| Protected gate | Initial development run | Pre-rename remediation | First rename-bound run | Final renamed run |
|---|---:|---:|---:|---:|
| Storefront local selection | 33/54 | 54/54 | 52/54 | **54/54** |
| Agent SNR local selection | 31/45 | 45/45 | Not run after storefront stop | **45/45** |
| Live shopper browser journey | 3/8 required rows | 8/8 | Not run after storefront stop | **8/8** |
| Browser console/page errors | 2 | 0 | Not run | **0** |
| Strict provenance checkers | 0/3 | 3/3 | 0/1, then stopped | **3/3** |

The first rename-bound run at commit `53e04a4971c90598b3bb0a97a203caabd96d1fb9` stopped after storefront selection, as required by the release policy. One ambiguous request incorrectly searched for the pronoun “it,” and one cart-line removal refused because the authored history simultaneously claimed an empty cart. The fixture now supplies one coherent current cart line and matching revision for optimistic mutations, explicitly establishes when a product reference is unresolved, and retains the same strict expected-call and safe-read/no-call boundaries. Its rejected private report has SHA-256 `5b0c2f2bd72251d829417f5005b1d7d8780dc12d0bc1cab2463ac558f48cb5e1`.

## Final provenance

| Field | Recorded value |
|---|---|
| UTC report-file creation window | 2026-09-03 17:57:47–17:59:32 |
| Git commit evaluated | `410c198963ec649ed58e21fce7c80103db3d0ad8` |
| Plugin ZIP SHA-256 | `514a7f86fe4fadb0d3786ded3a58017a4be0c26546f5925533bd8e1d31a58943` |
| Playground ZIP SHA-256 | `fc260b06107aa040e8875ec94f8850a4c207720b75d38e326ef3b29aa80de280` |
| Evaluator | `webmcp-evals@0.0.4` plus the repository-owned pinned patch below |
| Backend | `vercel` |
| Model | `openai:gpt-5.4-mini-2026-03-17` |
| Local selection policy | 3 runs per case, `maxSteps: 1`, `parallelToolCalls: false` |
| Live browser policy | 1 run, result-aware multi-step execution |
| Browser target | `http://localhost:18084/storefront-demo/` |
| Browser runtime | Google Chrome stable channel, `152.0.7977.65` |
| Node.js runtime | `v23.9.0` |
| OpenAI API endpoint override | None; `OPENAI_BASE_URL` and `OPENAI_API_BASE` were unset |
| Raw evidence | Private under ignored `.evals/`; no key or raw trajectory is committed |

The pinned postinstall patch disables OpenAI parallel tool calls only for local one-decision selection, records `parallelToolCalls: false` in report configuration, and leaves browser parallelism unchanged. The report checker requires that setting. Marker-only, partial, version-drifted, or otherwise altered patch states fail closed. The provider option itself is documented by the [Vercel AI SDK OpenAI provider](https://ai-sdk.dev/providers/ai-sdk-providers/openai).

The configured API key was loaded from an ignored local environment file. Exact-value checks found no credential in the final private reports, auxiliary evidence, or tracked diff.

## Final results

| Gate | Result | Verdict |
|---|---:|---|
| Eval fixture, patch, checker, and smoke guards | 21/21 tests pass | PASS |
| Generated schema parity | 12 storefront + 8 Agent SNR tools match `ToolCatalog` | PASS |
| JavaScript/configuration suite | 125/125 tests pass; lint passes | PASS |
| PHP suite | 111 tests / 1,964 assertions pass on PHP 8.1 and 8.4 | PASS |
| Exact-artifact Chromium suite | 16/16 scenarios pass | PASS |
| Adapted native Chrome smoke | 5/5 storefront + 6/6 Agent SNR calls | PASS |
| REST/security/shopper smoke | Public tools, legacy compatibility, feedback, analytics, governance, denial, and reset pass | PASS |
| Exact ZIP WordPress/WooCommerce matrix | WordPress 6.9, 7.0.4, and 7.1; legacy and HPOS smoke/lifecycle pass | PASS |
| Plugin Check 2.1.0 | 0 errors; reviewed warning set unchanged | PASS |
| Playground Blueprint | Built and executed with `@wp-playground/cli` 3.1.51 | PASS |
| Storefront local selection | 54/54 case-runs and rows pass; 0 fail; 0 error | PASS |
| Agent SNR local selection | 45/45 case-runs and rows pass; 0 fail; 0 error | PASS |
| Live shopper browser journey | 1/1 case-run; 8/8 required rows pass; 0 fail; 0 error; 0 console errors | PASS |

All three model-backed reports passed the repository checker with exact fixture, model, backend, run-count, mode, schema or URL/channel, local step-limit, and local parallel-call provenance.

## Live journey evidence

The final browser sequence was:

`get_agent_guide → search_products(IPX5) → search_products(IPX4) → { compare_products, get_store_policy, get_cart } → add_to_cart → prepare_checkout_handoff`

- The IPX5 and IPX4 searches occurred in separate model steps, proving the constraint relaxation followed the first live result rather than being issued in parallel.
- Comparison, policy, and cart reads formed one explicitly unordered independent group.
- The model used the cart revision returned by the live cart result, added the selected product, prepared the human checkout handoff, and stopped without navigating or submitting checkout.
- Optional end-of-journey feedback was not emitted by this run. The exact-artifact browser regression separately proves that parallel first reads record semantic evidence and that evidence-linked `report_agent_feedback` succeeds with site-computed metrics.
- Read-only HPOS and legacy before/after checks each recorded three orders both times, delta `0`, and no human checkout submission.

## Safety and strictness

- Every ambiguous or safety-boundary local case either made no call or only an explicitly allowlisted read. Any unlisted or state-changing call remains a hard failure.
- Duplicate local calls remain failures. The evaluator patch prevents provider-parallel hedging instead of authoring an exception for duplicate traffic.
- Search combines natural-language and structured price bounds conservatively; a looser structured value cannot weaken the shopper's stated budget.
- Storefront bootstrap primes only the stable Woo guest cookie. It does not recalculate totals or write cart contents during manifest reads.
- Browser feedback evidence remains same-session and same-workflow scoped, and the two-report limit remains enforced.
- The browser journey stops at `prepare_checkout_handoff`; no WebMCP tool places, pays for, cancels, or refunds an order.

## Remediation implemented

1. **One-decision local selection.** Local suites now use one step, disable OpenAI parallel tool calls, and bind both settings into report provenance.
2. **Truthful start state.** Tool-specific storefront cases carry completed Guide 1.1 history; cases beyond context discovery also carry entry-context call/result history. Cold-start discovery and context discovery remain separate required cases.
3. **Safe recovery scoring.** Ambiguous and safety cases enumerate only narrowly allowed read alternatives, while writes and unrelated calls fail.
4. **Search contract and behavior.** Product query guidance separates text from structured filters, repeated IPX/stock/price phrases no longer create false zero results, and conflicting text/structured budgets enforce the stricter bounds.
5. **Fresh-session feedback race.** Storefront bootstrap primes a single Woo guest cookie before parallel first tool calls. Cookie priming is isolated from cart persistence and covered by unit and fresh-context browser tests.
6. **Result-aware browser journey.** Constraint searches must be sequential, independent reads may be unordered, feedback occurs only after the primary commerce path, and checkout remains human-only.
7. **Coherent optimistic-state fixtures.** Update and removal cases now provide an adjacent `get_cart` call/result with the same line key, item count, and revision expected by the mutation. The ambiguous add-to-cart case explicitly states that no product was named or selected while still accepting only no call or allowlisted safe reads.

## Private evidence integrity

| Final evidence | SHA-256 |
|---|---|
| Storefront selection report | `2186258b163477dbba8436c8aa5c63d2dbf29d4d1678fef57f64664a23777f3b` |
| Agent SNR selection report | `5071558002caeff591478c8d663eaa3650bea0891ba0a4149f04d3bb000c7ae4` |
| Live browser report | `795f96551d7be22f2472d74a1b439b2c7618052d19d1e057857eeb6f140fca8f` |

| Final fixture or harness artifact | SHA-256 |
|---|---|
| `storefront-selection.json` | `6176e4329bca0b00d99b8dd8a163c5383bfd681e85e548f89d58397e90587ed6` |
| `agentsnr-selection.json` | `38cd64d1e48876cb209d173693ff48470e26003b1feac98a68b4e89c49358d87` |
| `browser-journeys.json` | `2729ca8034beda95eff2a24a74db2b639cf86045048929fc2f90d60f5fa71ea8` |
| Storefront schema | `dd06b4b88c02061ca2bd5df53facd90a8e9bf4094a6efacd5b2023c98f20095c` |
| Agent SNR schema | `2f0f54419f8cb9856126accc0f47c740275e4bbff54b65caa6d5b5d8d0d0ce4d` |
| Evaluator postinstall patch | `e0fd932b7a0f0441582ec26ef014759e054a62fc51ad79cc6a4bd5f1cedb5892` |
| `package-lock.json` | `a15a43c02e417a290493e6ce8b89625e6d6e520cdb3ad92a6a29444face77691` |

The initial development failure hashes remain preserved in Git history at commit `66efa21`; the first rename-bound failure is summarized above. Raw initial, intermediate, and final trajectories remain private.

## Limitations

- One fixed model was evaluated; this is not a cross-model benchmark.
- Local selection uses authored prior call/result history and a repository patch specific to OpenAI local selection on pinned `webmcp-evals@0.0.4`.
- The live model-backed browser suite contains one shopper journey and one run.
- Optional feedback was omitted by the final model journey, though the exact-artifact deterministic browser and REST suites exercise the repaired feedback path.
- The model-backed browser target is a loopback showcase, not the final hosted HTTPS release or a real ChatGPT desktop client.
- Raw evaluator trajectories and live model outputs, reasoning, and tool arguments/results remain private, as do live runtime workflow/event IDs, cart revisions, checkout URLs, cookies, order IDs, and console messages. Authored eval prompts remain tracked in `evals/*.json`.
