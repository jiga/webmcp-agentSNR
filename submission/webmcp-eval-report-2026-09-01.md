# Live WebMCP evaluation report — September 1–2, 2026

Status: **PASS — strict model-backed release gate cleared after remediation**

> **Historical evidence notice:** this report is bound to pre-public engineering commit `e4d9c86b2754c735094b1dc8437fbd007d3e557a` and its recorded artifact hashes. The later AgentSNR technical-identity rename did not change public tool names or workflow behavior, but protected model-backed suites have not yet been rerun against the renamed package. Mechanically updated current fixture labels below are navigation aids, not a claim that the 2026-09-02 raw reports were generated from the renamed filenames.

The initial protected run exposed real harness, search, session-correlation, and journey-design defects. The fixes were implemented without allowing duplicate or state-changing calls to bypass the gate. The same fixed model now passes every authored storefront, Agent SNR, and live-browser requirement.

## Before and after

| Protected gate | Initial result | Remediated result |
|---|---:|---:|
| Storefront local selection | 33/54 case-runs fully passed | **54/54 passed** |
| Agent SNR local selection | 31/45 case-runs fully passed | **45/45 passed** |
| Live shopper browser journey | 0/1 case-runs; 3/8 required rows passed | **1/1 case-run; 8/8 required rows passed** |
| Browser console/page errors | 2 | **0** |
| Strict provenance checkers | 0/3 passed | **3/3 passed** |

## Final provenance

| Field | Recorded value |
|---|---|
| UTC report-file creation window | 2026-09-02 05:25:44–05:26:32 |
| Git commit evaluated | `e4d9c86b2754c735094b1dc8437fbd007d3e557a` |
| Plugin ZIP SHA-256 | `38b1b8106f255b051ff07c953afb6db3be4504df85ac1c4f454c69ec82a416fa` |
| Playground ZIP SHA-256 | `96a9ec44596a76a07e5d549ff486d35acd8c0292cd5878db6e361321664d3f89` |
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
| JavaScript/configuration suite | 112/112 tests pass; lint passes | PASS |
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
- A hashed private before/after WooCommerce snapshot recorded 20 orders both times, matching private ID-set digests, delta `0`, and no human checkout submission.

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

## Private evidence integrity

| Final evidence | SHA-256 |
|---|---|
| Storefront selection report | `67e214c23640526678194f260e6147d0ad810763a4c18935232aae378bea8e27` |
| Agent SNR selection report | `b29fbf7b704bc4e2c6541ee708dc4f5f133338bc3a376d7ae62ffa66b1bb9ce7` |
| Live browser report | `c183d849c869b3869ffd7611d630c924d3b0ad3226a45fee292a950ac60f3ae8` |
| Runtime and order-snapshot auxiliary evidence | `122eba2eb2f918514f7d16460d9a170a0caa79ca62d83f8f367b6a4739e6405a` |

| Final fixture or harness artifact | SHA-256 |
|---|---|
| `storefront-selection.json` | `775a7ec44618806c8d6eef5582397c8ea14f3c01edd458e1883d917acab59dd0` |
| `agentsnr-selection.json` | `38cd64d1e48876cb209d173693ff48470e26003b1feac98a68b4e89c49358d87` |
| `browser-journeys.json` | `2729ca8034beda95eff2a24a74db2b639cf86045048929fc2f90d60f5fa71ea8` |
| Storefront schema | `dd06b4b88c02061ca2bd5df53facd90a8e9bf4094a6efacd5b2023c98f20095c` |
| Agent SNR schema | `2f0f54419f8cb9856126accc0f47c740275e4bbff54b65caa6d5b5d8d0d0ce4d` |
| Evaluator postinstall patch | `e0fd932b7a0f0441582ec26ef014759e054a62fc51ad79cc6a4bd5f1cedb5892` |
| `package-lock.json` | `e03347c02dfdb6792c42c88776c544883e28e0bb85e2f4a53f12975a08bec119` |

The initial failed report hashes remain preserved in Git history at commit `66efa21`; raw initial and final trajectories remain private.

## Limitations

- One fixed model was evaluated; this is not a cross-model benchmark.
- Local selection uses authored prior call/result history and a repository patch specific to OpenAI local selection on pinned `webmcp-evals@0.0.4`.
- The live model-backed browser suite contains one shopper journey and one run.
- Optional feedback was omitted by the final model journey, though the exact-artifact deterministic browser and REST suites exercise the repaired feedback path.
- The model-backed browser target is a loopback showcase, not the final hosted HTTPS release or a real ChatGPT desktop client.
- Raw evaluator trajectories and live model outputs, reasoning, and tool arguments/results remain private, as do live runtime workflow/event IDs, cart revisions, checkout URLs, cookies, order IDs, and console messages. Authored eval prompts remain tracked in `evals/*.json`.
