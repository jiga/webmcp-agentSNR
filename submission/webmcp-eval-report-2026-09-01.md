# Live WebMCP evaluation report — September 1, 2026

Status: **FAIL — model-backed release gate is not cleared**

The deterministic contracts and native Chrome invocation layer pass. The fixed-model selection and live multi-step journey suites do not meet the repository's strict 100% all-pass policy. This is a useful release finding: it identifies candidate contributors involving start state, mock output, safe no-call scoring, journey ordering, and the feedback path that must be investigated before model-backed evidence is marked complete.

## Provenance

| Field | Recorded value |
|---|---|
| UTC report-file creation window | 2026-09-01 23:57:09–23:59:22 |
| Git commit evaluated | `e853062bb097e1c94fcb2a4fec64f019b3c9676b` |
| Plugin ZIP SHA-256 | `7cd4e74ca39c3a9dd4729a0deca3916585c87e2330cb6a65db4194d912a8ba5a` |
| `webmcp-evals` | `0.0.4` |
| Backend | `vercel` |
| Model | `openai:gpt-5.4-mini-2026-03-17` |
| Local selection runs | 3 per case |
| Live browser runs | 1 |
| Browser target | `http://localhost:18084/storefront-demo/` |
| Browser runtime | Google Chrome stable channel, `152.0.7977.65` |
| Node.js runtime | `v23.9.0` |
| OpenAI API endpoint override | None; `OPENAI_BASE_URL` and `OPENAI_API_BASE` were unset |
| Raw evidence | Private under ignored `.evals/`; no key or raw trajectory is committed |

The configured API key was loaded from an ignored local environment file. Credential-pattern and repository-status checks found no key in the proposed tracked diff or the raw report files.

## Results

| Gate | Result | Strict verdict |
|---|---:|---|
| Fixture/report/smoke guard tests | 17/17 tests pass | PASS |
| Generated schema parity | 12 storefront + 8 Agent SNR tools match `ToolCatalog` | PASS |
| Adapted native Chrome smoke | 5/5 storefront + 6/6 Agent SNR calls | PASS |
| Storefront local selection | 33/54 case-runs fully pass; 33/78 step rows pass; 45 fail; 0 error | FAIL |
| Agent SNR local selection | 31/45 case-runs fully pass; 40/64 step rows pass; 24 fail; 0 error | FAIL |
| Live shopper browser journey | 0/1 case-runs; 3/8 required rows pass; 5 fail; 0 evaluator error | FAIL |

The repository provenance checker exited nonzero for all three model-backed reports:

- storefront: reported trajectory/expected-call order diverged from the selected fixture;
- Agent SNR: reported trajectory/expected-call order diverged from the selected fixture;
- browser: strict all-pass failed with five failed rows.

First-call selection was stronger than the strict trajectory score:

| Surface | Correct first call |
|---|---:|
| Storefront | 36/54 case-runs (66.7%) |
| Agent SNR | 40/45 case-runs (88.9%) |

## Safety evidence

- Across every storefront and Agent SNR case whose authored `expectedCall` was `null`, the model made **zero state-changing calls**. Deviations were conservative reads such as guide, cart, workflow-query, overview, or diagnostics calls.
- An operator-observed WooCommerce query immediately before and after the live browser run returned the same 16-order count and matching private ID-set digests. The run therefore created **zero WooCommerce orders**.
- The browser never reached `add_to_cart` or `prepare_checkout_handoff`, and no human checkout was submitted.
- Two browser console errors were recorded for HTTP 400 `report_agent_feedback` requests; the corresponding tool-result envelopes exposed the safe public code `invalid_agent_feedback`.
- A separate operator-observed sequential probe using the same closed-schema zero-result feedback pattern succeeded with HTTP 200. This does not establish causality, but it narrows the follow-up toward browser trajectory/state ordering rather than a blanket conclusion that the base schema is unusable.

The order snapshots and sequential probe were terminal-side operator checks, not fields embedded in the three hashed evaluator JSON reports. They are labeled separately here so their evidence strength is not overstated.

## Case-level findings

### Storefront

- 10 of 18 cases passed all three runs, including exact cart writes, guide discovery, product/cart reads, stale-cart recovery, constraint relaxation, capability no-call, and safe checkout preparation.
- The guide was called 19 times. Many isolated cases received a guide read before the fixture's expected tool or no-call outcome; interpreting “use once at the start” as a prerequisite is a plausible explanation that requires a controlled rerun.
- Context, search, and policy cases often selected the intended tool after the guide, then made additional calls after the local evaluator returned an empty mock result. Whether richer mock results prevent those calls remains a hypothesis to test.
- The structured-feedback case selected `report_agent_feedback` in all three runs but missed an exact argument constraint.
- No-call purchase/refund cases failed only because of safe guide reads, not because the model attempted an order, payment, cancellation, or refund action.

### Agent SNR

- 9 of 15 cases passed all three runs, including overview, workflow query/explanation, funnel, diagnostics, session controls, recovery, and raw-secret refusal.
- `get_opportunity_signals` was invoked 21 times, with repeated calls following empty mock results. A controlled mock-output rerun is needed before treating the empty result as causal.
- Slow-tool analysis correctly selected `get_tool_health` first, then expanded into overview/funnel reads.
- Missing-workflow and two safety cases used conservative read tools; no public policy elevation or commerce mutation was attempted.

### Live browser journey

The actual safe call sequence was:

`get_agent_guide → search_products → get_store_policy → report_agent_feedback → search_products → get_cart → report_agent_feedback`

The model recovered the search constraint but reordered policy and feedback, skipped comparison, cart mutation, and checkout preparation, and ended early. Competition between optional feedback and the core commerce path is a plausible explanation, not yet a controlled finding.

## Likely contributors and recommended fixes

1. **Test single-step fixture construction.** Add minimal `mockOutput` to every positive local expected call and run local selection with an explicit one-step limit. Today all 25 positive selection calls omit `mockOutput`; compare the rerun to determine whether `{}` contributed to continued work.
2. **Test an explicit guide precondition.** For tool-specific cases, include prior context that Guide 1.1 was already read. Keep a separate case that proves guide-first selection. Compare both versions before changing the real guide.
3. **Measure safety separately from literal silence.** A safe read-only clarification is not equivalent to an unsafe action. Retain strict no-call cases where appropriate, but add an explicit zero-state-changing-call metric.
4. **Test a staged browser journey.** Move feedback to the end of the prompt or split discovery/recovery from cart/handoff evaluation, then compare whether optional telemetry still appears before the user's primary outcome.
5. **Investigate live feedback ordering.** Reproduce the model's exact browser sequence in a focused test and identify why valid-shaped, same-workflow search evidence returned `invalid_agent_feedback` while the sequential probe succeeded.
6. **Re-run before release clearance.** Use the same dated model, backend, fixtures, schemas, run counts, checker, and exact artifact. The model-backed gate remains failed until every authored required row passes and browser console errors are zero.

## Private raw-report integrity

| Report | SHA-256 |
|---|---|
| Storefront selection | `1ab902804b07f4f13bd052a82eb0f0c1ca16d06d7699b2321fea1570fe1b7919` |
| Agent SNR selection | `fdfd3b94a435d853c9a58ee2cce62e5041054f8f806fc45c6db6dc4a70b398d4` |
| Live browser journey | `9ee90df182fbb0f60009a12ce298377f2a5b4f164ac24fb079aa29c15645c475` |

Fixture and schema hashes:

| Artifact | SHA-256 |
|---|---|
| `storefront-selection.json` | `6a82b60c569429b44b9c80a2a302b1a559d7fcf073b772fe549dae92f25f94b4` |
| `agentops-selection.json` | `6d9197a46d951096c076b0d42f65b57c668b6b70fc0f84b66f8753689f0e50bb` |
| `browser-journeys.json` | `e822a0e72426ebe8db31ed34be5af6a0d6694df073455629d35915eb7e13e0ea` |
| Storefront schema | `ec7a112a51179480cbb680d740dda3f7288f1d00b105d9834db20b7444097245` |
| Agent SNR schema | `ab4927ca19b8eb7deac28f5e9c3f973b94f896adb92601fba8bd3e8f7a4f9fdc` |
| `package-lock.json` | `e03347c02dfdb6792c42c88776c544883e28e0bb85e2f4a53f12975a08bec119` |

## Limitations

- One fixed model was evaluated; this is not a cross-model benchmark.
- Local selection uses mocked execution and currently lacks realistic mock outputs.
- The live browser suite contains one long shopper journey and one run.
- Raw prompts, reasoning, tool arguments/results, workflow/event IDs, cart revisions, checkout URLs, cookies, and console messages remain private.
