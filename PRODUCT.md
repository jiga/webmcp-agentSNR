# Agent SNR product direction

**Agent outcome monitoring for WordPress.**

## Problem statement

Website operators can inspect model traces in generic LLM tools or replay human browser sessions in digital-experience products, but neither view explains the complete website-agent journey: which WebMCP tools were available, what the agent invoked, what policy allowed or denied, where the human took over, and whether WooCommerce recorded a paid, cancelled, or refunded outcome.

Agent experience monitoring is the broader category. **Agent SNR** is the product: it turns WebMCP activity into verified operational and business signal while suppressing unactionable noise. SNR uses its established engineering meaning: **signal-to-noise ratio**.

> See what agents could do, what they actually did, where humans intervened, and what happened next.

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
| Signal | Recorded tool failure, denied invocation, or unsupported capability request |
| Workflow Replay | Event-sourced redacted timeline; never described as a screen recording |

## Core MVP

### Must have

1. **Monitor** — completion, failure/denial, latency, human handoffs, attributed orders, refunds, and net outcome.
2. **Agent Sessions** — a session-scoped workflow feed that opens a redacted Workflow Replay.
3. **Signals** — a loaded-snapshot queue derived from top tool errors, denied/failed invocations, and unsupported capability requests.
4. **Tools** — availability, policy state, reliability, latency, and commerce contribution.
5. **Controls** — server-authoritative session restrictions, persistent policy, and emergency stop.

### Explicitly excluded from this iteration

- DOM/video recording or claims of pixel-level session replay;
- raw prompts, customer identity, addresses, payment data, or arbitrary payload capture;
- persistent issue assignment, comments, alert delivery, anomaly detection, and saved segments;
- model token/cost analytics and model-quality evaluation;
- changing internal plugin slugs, namespaces, REST routes, or tool contracts.

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

## Full vision

Later phases can add saved workflow segments, durable Signal-to-Issue grouping, alert thresholds/anomalies, ownership and resolution state, annotations/highlights, visitor-session-to-agent-session correlation, and cross-site aggregate monitoring.
