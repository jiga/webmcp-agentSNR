# Agent SNR product direction

**Agent outcome monitoring for WordPress.**

## Problem statement

Website operators can inspect model traces in generic LLM tools or replay human browser sessions in digital-experience products, but neither view explains the complete website-agent journey: which WebMCP tools were available, what the agent invoked, what policy allowed or denied, where the human took over, and whether WooCommerce recorded a paid, cancelled, or refunded outcome.

Agent experience monitoring is the broader category. **Agent SNR** is the product: it turns WebMCP activity into verified operational and business signal while suppressing unactionable noise. SNR uses its established engineering meaning: **signal-to-noise ratio**.

> See what agents did. Hear what they experienced. Discover what your site is missing.

## Target users

- **Primary:** a WordPress/WooCommerce operator investigating one agent-assisted shopper journey and deciding whether a tool or policy needs attention.
- **Secondary:** an engineer validating WebMCP contracts, execution reliability, latency, and attribution evidence.

## Product analogy

The recognizable workflow combines:

- [Fullstory Session Replay](https://help.fullstory.com/hc/en-us/articles/360020828573-Getting-Started-with-Session-Replay): aggregate metric → matching session → timestamped replay;
- [LogRocket Issues](https://docs.logrocket.com/docs/issues-2026): raw Signal → grouped Issue → resolution;
- [Datadog RUM hierarchy](https://docs.datadoghq.com/real_user_monitoring/guide/understanding-the-rum-event-hierarchy/): explicit session/action/error objects;
- [Amplitude Agent Analytics](https://amplitude.com/docs/amplitude-ai/agent-analytics/taxonomy): agent session → turn → span, connected to business analysis;
- [Sentry Replay Details](https://docs.sentry.io/product/session-replay/replay-details/): one investigation timeline with activity, errors, traces, and tags.

Agent SNR differentiates through first-class WebMCP inventory and policy, browser/human handoffs, and deterministic WooCommerce order/refund attribution. Signal means evidence that changes an operator's decision—tool outcomes, policy denials, human handoffs, paid orders, refunds, and unsupported demand. Noise means high-volume activity that does not justify a decision; raw prompts, identities, arbitrary payloads, and speculative revenue attribution are intentionally excluded rather than promoted as business evidence.

## Core monitoring model

| Product object | Current implementation |
|---|---|
| Visitor session | Anonymous, server-issued demo-session scope |
| Agent session | One redacted storefront workflow |
| Invocation | One tool-call start plus one authoritative terminal event |
| Human checkpoint | Visible cart review, checkout handoff, and human order placement evidence |
| Outcome | Paid order, cancellation, refund, abandonment, or no commerce effect |
| Signal | Site-observed behavior, agent-reported feedback, or a site-verified outcome that can change an operator decision |
| Workflow Replay | Event-sourced redacted timeline; never described as a screen recording |

## Core MVP

### Must have

1. **Monitor** — completion, failure/denial, latency, human handoffs, attributed orders, refunds, and net outcome.
2. **Agent Sessions** — a session-scoped workflow feed that opens a redacted Workflow Replay.
3. **Signals** — a loaded-snapshot queue that keeps site-observed demand, agent-reported feedback, linked measurements, tool errors, denials, and unsupported capabilities visibly distinct.
4. **Tools** — availability, policy state, reliability, latency, and commerce contribution.
5. **Controls** — server-authoritative session restrictions, persistent policy, and emergency stop.

### Explicitly excluded from this iteration

- DOM/video recording or claims of pixel-level session replay;
- raw prompts, customer identity, addresses, payment data, or arbitrary payload capture;
- persistent issue assignment, comments, alert delivery, anomaly detection, and saved segments;
- model token/cost analytics and model-quality evaluation;
- changing internal plugin slugs, namespaces, or REST routes after the release candidate is frozen;
- free-form feedback capture, caller-supplied metric values, or automatic changes to catalog, policy, attribution, or inventory;
- public cross-session demand claims; v0.1 Signals remain explicitly scoped to the current private demo session.

Search signals compute counts, stock coverage, and highest matching public facts across the plugin's bounded 200-product catalog scan before the public response is sliced to at most eight products. When an in-stock search returns zero, one bounded all-stock scan distinguishes no match within that bounded scan from an existing out-of-stock match; out-of-stock matches remain absent from the tool result while their public product ID can anchor the merchant's inventory signal. Grouped measurements are explicitly one deterministic workflow sample—latest linked agent feedback when available, otherwise the latest site observation—and are never presented as a multi-workflow aggregate.

### Agent Guide and feedback contract

The storefront publishes one versioned **Agent Guide** for both humans and agents. Guide 1.1 declares top-level co-browsing as the supported mode, unattended execution as unsupported, the shopper and operator journeys, answer/action/telemetry effects, zero Sensitive Action tools, the subtotal/final-total boundary, privacy, optional feedback triggers, and the two-report limit.

Public discovery is outcome-scoped: 12 storefront tools on the shopper page and 8 Agent SNR tools on the operator page. Two older capability-gap abilities remain registered for server-side compatibility but are absent from WebMCP discovery, so agents receive one canonical public tool per intent. The non-purchasing handoff is named `prepare_checkout_handoff`; normal WooCommerce UI retains customer data, terms, final totals, order placement, payment, cancellation, and refund authority.

Automatic observation does not depend on an agent volunteering feedback. Zero-result and constrained low-coverage searches create privacy-preserving `site_observed` signals from a canonical demand signature. The agent may separately submit an `agent_reported` outcome, reason, ratings, owner-action suggestion, same-workflow evidence IDs, and a request for allowlisted measurements. WordPress computes every metric value from catalog, workflow, and WooCommerce evidence; unavailable conversion/value remains pending rather than becoming a false zero.

## Signature experience

The memorable element is an **Agent Journey**:

`VISITOR SESSION → AGENT WORKFLOW → TOOL INVOCATION → HUMAN CHECKPOINT → VERIFIED OUTCOME`

An operator starts with a recorded signal or outcome, then inspects the session-scoped Agent Sessions feed and returned redacted tool/commerce evidence in chronological order, with partial coverage explicitly disclosed.

## Success criteria

- [x] A new operator can answer “what did the agent do?” from the Agent Sessions section without learning the internal event schema.
- [x] A failed or denied tool is visible as a Signal with affected counts and returned terminal-code evidence.
- [x] A paid/refunded outcome remains visibly distinct from tool success.
- [x] Every monitoring label remains accurate under the current privacy and attribution implementation.
- [x] Existing WebMCP, REST, WooCommerce, and browser acceptance tests remain green.
- [x] A zero-result search is visible even when no agent feedback is submitted.
- [x] Agent testimony, site observation, and site-verified measurements are never merged into one trust label.

## Full vision

Later phases can add saved workflow segments, durable Signal-to-Issue grouping, alert thresholds/anomalies, ownership and resolution state, annotations/highlights, visitor-session-to-agent-session correlation, and authenticated thresholded cross-session aggregate monitoring.
