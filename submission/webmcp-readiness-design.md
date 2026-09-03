# Agent SNR WebMCP readiness design

Status: **Accepted for implementation**

Decision date: September 1, 2026

Scope: hackathon release `v0.1.0`

## Why this design exists

The referenced [five-resource hackathon post](https://x.com/0xidanlevin/status/2094784117107839001) is not a request to add five unrelated integrations. Its useful product direction is a closed quality loop:

1. design a complete user journey from the desired outcome;
2. expose a small, unambiguous set of tools for that journey;
3. test discovery, selection, arguments, state, recovery, and safety;
4. inspect the experience in a real agent client;
5. publish the deployed implementation so other agents can discover it.

Agent SNR already closes the business-observability loop. This design closes the WebMCP product-quality loop without weakening the existing human checkout, privacy, or evidence boundaries.

## Product outcomes

### Shopper

Find and evaluate a viable product, prepare a reversible cart, and stop at a human-owned checkout with the important trade-offs visible.

### Human

Review final tax, shipping, discounts, and total; provide customer information; accept terms; and explicitly place the order in ordinary WooCommerce UI.

### Operator

Connect the agent's journey to verified paid, abandoned, cancelled, or refunded outcomes, then decide whether to improve product data, inventory, policy, or the tool layer.

## Product boundary

Agent SNR v0.1 is a **top-level co-browsing experience**. Unattended remote execution is unsupported. The public WebMCP catalog contains no Sensitive Action that can place, cancel, or refund an order, accept terms, submit customer data, or process payment.

The July journey article mentions `requestUserInteraction()`, but that method is absent from the [August 26 Community Group draft](https://webmachinelearning.github.io/webmcp/); a possible replacement remains an [open proposal](https://github.com/webmachinelearning/webmcp/pull/204). The release therefore keeps the current, reliable WooCommerce URL handoff instead of depending on an unstable browser API.

WebMCP is described as a proposed API and W3C Community Group draft, not as a W3C standard.

## Two outcome-oriented surfaces

No agent receives all catalog contracts at once. Each top-level page exposes only the tools needed for its user outcome.

| Surface | Outcome chain | Public discovery |
|---|---|---|
| Storefront | understand → discover → inspect/compare → verify policy → prepare cart → hand off → optional feedback | 12 canonical tools |
| Agent SNR | overview/query → explain → health/funnel/signals → diagnose → session-only restrict/restore | 8 canonical tools |

The catalog retains two legacy compatibility abilities, `report_capability_gap` and `get_capability_gaps`, but removes them from default WebMCP discovery. Their canonical replacements are structured feedback/automatic observations and unified opportunity signals. This preserves existing internal compatibility while giving agents one public tool per intent.

`checkout_handoff` becomes `prepare_checkout_handoff` before the public release. The new name makes initiation, effect, and the absence of purchase authority clear to agents and directory classifiers.

## Canonical shopper journey

| Stage | Tool | Required behavior |
|---|---|---|
| Understand | `get_agent_guide` | Return supported journeys, effects, privacy, feedback triggers, execution mode, and the human boundary. |
| Discover | `search_products` | Use public catalog facts. Zero/low coverage records a site-observed signal and returns a recoverable constraint explanation. |
| Inspect | `get_product` | Return one product's public facts. Merchant-authored content remains marked untrusted. |
| Compare | `compare_products` | Compare two to four stored facts. Missing facts remain missing; no guessed ranking is presented as objective. |
| Verify | `get_store_policy` | Return published policy facts and bounded evidence. |
| Prepare | `get_cart`, `add_to_cart`, `remove_from_cart`, `update_cart_quantity` | Use optimistic cart revisions and keep returned state, visible UI, and other open tabs synchronized. |
| Handoff | `prepare_checkout_handoff` | Validate the current cart and reveal a checkout link. Do not navigate, create an order, accept terms, submit data, or process payment. |
| Feedback | `report_agent_feedback` | Optionally submit bounded testimony linked to same-workflow evidence. The site computes requested metrics. |
| Human checkout | No WebMCP tool | The person reviews the final all-in amount and places the no-charge demo order in WooCommerce. |

The price shown before checkout is a cart subtotal or estimate. Address-dependent tax, shipping, fees, and the final total belong to the human checkout screen.

## Canonical operator journey

| Stage | Tool group | Required behavior |
|---|---|---|
| Monitor | `get_agent_analytics_overview`, `query_agent_workflows` | Establish the current private-session operating picture and list candidate workflows. |
| Investigate | `explain_agent_workflow`, `get_agent_conversion_funnel`, `get_tool_health`, `get_opportunity_signals` | Return redacted chronological evidence, tool performance, journey progression, and distinct signal sources. |
| Diagnose | `run_webmcp_diagnostics` | Run read-only public compatibility checks without exposing secrets or paths. |
| Govern | `set_tool_enabled` | Restrict or restore one storefront tool for this demo session only; never elevate a site denial. |

## Trust ladder

| Layer | Meaning | Agent SNR contract |
|---|---|---|
| Answer | Read first-party facts without changing state. | `readOnlyHint: true`; untrusted merchant/external text is explicitly annotated. |
| Action | Reversible, non-committing current-session change. | Cart changes and session-only availability controls use `readOnlyHint: false`, CSRF, origin checks, rate limits, and idempotency. |
| Telemetry | Bounded analytics testimony, not a commerce action. | Structured feedback is optional, rate-limited, closed-schema, and never changes catalog, policy, attribution, or inventory. |
| Sensitive Action | Money, legal acceptance, identity, or irreversible commitment. | No public WebMCP tool in v0.1. Normal WooCommerce UI owns the checkpoint. |

## Tool-contract quality

The canonical `ToolCatalog` remains the single source of truth. Release gates enforce the current Chrome guidance:

- tool and input-property names are at most 30 characters;
- tool descriptions are at most 500 characters and state when to use the tool, its effect, and important non-effects;
- every public input property has a useful description of at most 150 characters;
- every input object rejects unknown properties;
- read-only and untrusted-content annotations match actual behavior;
- public output collections are bounded, paginated, or explicitly truncated;
- the hard server result ceiling remains 8 KiB while common agent-facing results target the recommended 1.5K characters; measured exceptions are documented rather than silently truncated.

The broad context, feedback, and legacy gap descriptions must explicitly distinguish their intent so a model does not choose them interchangeably.

## Failure and recovery contract

Errors must name the failed prerequisite or constraint and tell the agent how to recover. The release evals cover at least:

- missing product or cart context;
- stale cart revision followed by `get_cart` and one retry;
- invalid ID, enum, date, cursor, or evidence reference;
- zero results followed by a single explicit constraint relaxation;
- disabled tool followed by manifest refresh and an alternative path;
- ambiguous outcome after a transport failure, requiring state inspection before retry;
- a request to place, cancel, or refund an order, for which no WebMCP purchase/refund tool exists.

## Evaluation architecture

No single harness proves the product. The release uses required gates plus optional supplemental evidence:

| Gate | What it proves | Release behavior |
|---|---|---|
| PHPUnit and JavaScript contracts | Exact catalog, schemas, annotations, character budgets, privacy, idempotency, and fixture parity | Blocking and deterministic |
| GoogleChromeLabs `webmcp-evals@0.0.4` smoke | Native Chrome discovery and successful invocation of safe, concrete read calls on both top-level surfaces | Blocking on disposable localhost; no model key |
| `webmcp-evals` local selection | Natural-language selection, argument extraction, paraphrases, ambiguity, and no-call safety | Protected/manual; fixed model and three runs |
| `webmcp-evals` browser journey | Live model trajectory and result-aware recovery | Protected/manual; JSON report is parsed because the CLI does not fail the process for ordinary mismatches |
| Playwright and Woo lifecycle | Cart revisions, visible UI, cross-tab state, human checkpoint, order creation, attribution, and refund net | Blocking and deterministic |
| Nekuda Workbench | Real-client schemas, manual happy/error calls, Audit findings, repeated evals, saved-call replay, logs, approvals, and User Mode co-browsing | Optional owner-run evidence against the frozen public release |
| WebMCP.com scanner/directory | Public registration, external classification, coverage, and discoverability | Optional owner-run evidence after top-level HTTPS deployment |

The pinned Evals CLI launches Chrome without its sandbox. Its smoke job is allowed only against the disposable localhost demo, with no provider secrets. Model-backed runs are never pointed at an arbitrary URL and their `.evals/` reports are private build evidence.

### Model-eval thresholds

- Core shopper and operator selection cases pass 3/3 on one explicitly recorded model/version.
- Every authored case and required step passes in every recorded run (100% all-pass).
- Safety cases produce zero incorrect state-changing calls.
- The full shopper browser run's last required commerce step is `prepare_checkout_handoff`; optional evidence-linked feedback may follow, while WooCommerce order count remains unchanged.
- Browser reports contain zero failed/error cases and zero uncategorized console errors.

Probabilistic eval evidence complements deterministic tests; it never replaces them.

## Workbench evidence contract

The repository provides a fill-in validation sheet. The release owner records:

- Workbench version and test date;
- both public top-level page URLs;
- discovered tool counts and schemas;
- manual happy-path, invalid-input, stale-state, and disabled-tool calls;
- Audit score and every remediated or accepted finding;
- saved-call replay after a manifest refresh;
- model/provider name, repeated eval pass rate, and User Mode journey result;
- approval behavior at reversible writes and the normal human checkout handoff;
- the private location of exported logs.

Extension installation, keys, and external account actions stay owner-controlled. Logs or screenshots containing keys, cookies, session material, or customer data are never committed.

## Directory readiness

The [WebMCP.com methodology](https://webmcp.com/methodology) weights usability most heavily, then journey coverage and mechanical quality. The release therefore optimizes for one coherent journey, precise descriptions, strict schemas, and honest Answer/Action classification rather than tool count.

If pursuing optional WebMCP.com evidence after the exact release is deployed to stable top-level HTTPS URLs, the owner:

1. verifies both surfaces register on page load;
2. runs the public scanner and resolves load, empty-API, classification, and schema findings;
3. verifies the site through the read-only [directory API](https://webmcp.com/api-docs);
4. submits the public URL and contact email for human review;
5. records the final directory URL in the submission.

Localhost cannot be listed, and the repository does not fabricate or autonomously perform the human submission.

## Deliberate non-goals

- no autonomous purchase, cancellation, refund, or arbitrary order lookup;
- no remote headless write mode;
- no installation of a browser extension or submission to an external directory without owner action;
- no raw prompt, raw search text, free-form feedback, customer identity, address, payment data, cookie, token, or arbitrary payload storage;
- no new tool merely to mirror a button or REST endpoint;
- no claim that agent-reported feedback is verified fact or that opportunity context is lost revenue.

A same-session, read-only order-status journey is a reasonable later addition, but it is not required to prove the v0.1 shopper → human → operator loop.

## Acceptance checklist

- [ ] Public discovery exposes 12 storefront and 8 Agent SNR tools; two legacy abilities are not registered.
- [ ] `prepare_checkout_handoff` is the only public handoff name in discovery, evals, UI dispatch, and submission artifacts; internal event/metric keys remain `checkout_handoff`.
- [ ] The Agent Guide declares co-browsing scope, unsupported unattended execution, two user outcomes, tool effects, optional feedback, subtotal/final-total boundary, and zero Sensitive Actions.
- [ ] Every public input property has a concise description and every name/description passes character-budget tests.
- [ ] Eval schemas are generated from and parity-checked against `ToolCatalog`.
- [ ] Positive, paraphrased, ambiguous, recovery, and no-call fixtures cover both surfaces.
- [ ] Pinned keyless native smoke passes both surfaces on disposable localhost.
- [ ] Existing deterministic browser/order/refund tests remain blocking.
- [ ] Workbench and directory owner templates are complete and linked from the release checklist.
- [ ] README, runbook, deck, screenshots, Devpost copy, verification report, ZIP, and Playground bundle agree with the exact release.

## Primary references

- [Original five-resource X post](https://x.com/0xidanlevin/status/2094784117107839001)
- [Nekuda WebMCP Workbench](https://chromewebstore.google.com/detail/nekuda-webmcp-workbench/amochnnbmnkjjlblolhpddkokhnalkjp)
- [GoogleChromeLabs WebMCP Evals](https://github.com/GoogleChromeLabs/webmcp-tools/tree/main/webmcp-evals)
- [WebMCP.com directory](https://webmcp.com/) and [API](https://webmcp.com/api-docs)
- [Journey design guide](https://webmcp.com/blog/building-user-journeys-with-webmcp)
- [WebMCP resources](https://webmcp.com/resources)
- [Chrome workflow design](https://developer.chrome.com/docs/ai/webmcp/build-tools)
- [Chrome eval guidance](https://developer.chrome.com/docs/ai/webmcp/evals)
- [Chrome tool security](https://developer.chrome.com/docs/ai/webmcp/secure-tools)
- [Current WebMCP Community Group draft](https://webmachinelearning.github.io/webmcp/)
