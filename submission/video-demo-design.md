# Judge-facing WebMCP demo design

## Problem with the first cut

The first 2:29 video records real hosted tool executions, but the viewer mostly sees resulting UI state while narration describes what the agent did. That is credible product evidence, but it is not sufficiently explicit as a WebMCP agent demonstration. A judge should never have to infer that a browser agent selected and invoked page tools.

## Locked judging story

The replacement video is a live, two-persona demonstration on the frozen hosted project:

1. **Shopper + agent:** a shopper asks for an IPX5 waterproof backpack under $100. A visible Browser Agent panel discovers the page's tools, reads the Agent Guide, calls the real registered tools, encounters zero results, recovers to IPX4 alternatives, compares evidence, checks policy, prepares the cart, submits bounded feedback, and stops at human checkout.
2. **Human checkpoint:** the shopper reviews the ordinary no-charge checkout and explicitly places the fictional order. No WebMCP tool places an order or handles payment.
3. **Owner + agent:** a store owner asks what happened. The visible Browser Agent panel calls the real Agent SNR tools, retrieves analytics, opportunity signals, tool health, and the exact workflow explanation, then applies a session-only comparison-tool restriction.

## What must be visible

- The user's natural-language prompt.
- The selected registered WebMCP tool name and a compact, truthful subset of its arguments.
- The actual structured result status and decision-relevant evidence.
- The agent's concise interpretation and next action.
- The corresponding real storefront or Agent SNR UI updating beside the agent panel.
- The human checkout boundary and explicit click.
- The verified order and the owner-side attributed workflow.

The agent panel is a recording aid, not a simulated result surface: every displayed call and result is derived from the same `definition.execute()` invocation used by the hosted page.

## WordPress plugin explanation

Use no setup or installation footage. Explain the implementation in approximately twelve seconds while the live product is already visible:

> Agent SNR is a WordPress plugin. It registers top-level WebMCP tools in the shopper and owner pages. Calls stay same-origin, execute through the plugin's PHP endpoints, use WooCommerce as the commerce system of record, and write only a redacted event ledger that can be linked to verified outcomes.

No third-party logo, music, admin credential, customer data, or unrelated browser chrome appears. Descriptive product names appear only where necessary to explain compatibility and must be cleared by the entrant before upload.

## Target edit

| Time | Proof |
|---:|---|
| 0:00–0:12 | Working hosted product plus concise plugin architecture |
| 0:12–0:28 | Shopper prompt, tool discovery, and Agent Guide |
| 0:28–0:48 | IPX5 zero result becomes a site-observed opportunity |
| 0:48–1:12 | IPX4 recovery, comparison, and returns evidence |
| 1:12–1:35 | Reversible cart, direct agent feedback, and checkout handoff |
| 1:35–1:52 | Human reviews and places the no-charge order |
| 1:52–2:08 | Owner prompt and Agent SNR analytics/attribution calls |
| 2:08–2:32 | Signals and exact workflow replay with three evidence classes |
| 2:32–2:48 | Owner agent applies session-only governance; close |

Target runtime is 2:48, leaving twelve seconds below the hard three-minute limit.

## Acceptance criteria

- [ ] A first-time judge can identify both user stories without narration alone.
- [ ] At least one real registered WebMCP call and result is visibly paired on each surface.
- [ ] The video explicitly says how the WordPress plugin uses WebMCP.
- [ ] The shopper flow includes discovery, failure recovery, feedback, human handoff, and verified outcome.
- [ ] The owner flow includes analysis, evidence inspection, and a bounded action.
- [ ] Runtime is below 3:00 and the project is already functioning in the first 15 seconds.
- [ ] Audio is natural, clear, and synchronized to visible action.
- [ ] No prohibited third-party media, music, credentials, PII, or unrelated UI appears.
