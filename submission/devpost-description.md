# Devpost description draft

> Replace the bracketed links after the frozen public resources exist. Agent SNR still requires formal trademark and domain clearance before public launch.

## One-line pitch

**Agent outcome monitoring for WordPress.** Browser agents operate a real WooCommerce storefront, while merchants replay privacy-safe workflows, investigate signals, connect human handoffs to verified outcomes, and govern the WebMCP tool layer.

## The problem

Most WordPress agent integrations stop at exposing actions. A merchant can see that a tool was called, but not the complete path from shopper goal to product evidence, cart, human checkout, paid order, refund, failure, or unsupported request. Raw activity logs also make it easy to overclaim revenue and difficult to answer a practical question: what should the site operator improve or disable next?

## The solution

**Agent SNR** closes that loop locally inside WordPress. SNR retains its established meaning—**signal-to-noise ratio**—because the product separates verified business and reliability evidence from raw, unactionable agent noise:

1. A top-level storefront exposes narrow WebMCP tools for product context, discovery, comparison, published policy evidence, reversible cart changes, checkout handoff, and unsupported-demand reporting.
2. A redacted workflow ledger records one start and one terminal outcome for every authenticated tool request.
3. WooCommerce order and refund hooks preserve same-session product evidence and classify eligible agent-linked orders as direct, assisted, or influenced without double-counting revenue. Orders without qualifying evidence are excluded from attributed reporting rather than claimed.
4. A separate top-level Agent SNR surface exposes tools for the journey, Workflow Replay, tool health, capability signals, attributed revenue, diagnostics, and a restrictive session-only policy change.

The memorable loop is not “AI shopping.” It is a website observing and improving its own agent interface.

Workflow Replay is a privacy-safe event timeline, not DOM capture, video recording, or pixel reconstruction.

## Why WebMCP is the right fit

The workflows belong to the website. Prices, stock, product facts, policies, the current WooCommerce cart, WordPress permissions, and the human checkout UI already live there. WebMCP lets the page describe those capabilities directly to the browser agent instead of requiring brittle visual automation or a separate remote agent backend.

The submission uses the current imperative API, `document.modelContext.registerTool()`, in the top-level document. A dynamic manifest is generated from the same first-party registry used to register WordPress Abilities. The browser sends structured inputs to a same-origin WordPress REST execution gateway and displays every meaningful result in shared human-visible state.

WebMCP also makes the governance demonstration possible: the merchant asks Agent SNR to disable product comparison for this demo session. The server applies the restriction immediately, invalidates the manifest, and the browser replaces the prior registration set. Another judge’s session is unaffected.

## Human-agent collaboration

The agent does the repetitive research and preparation: it finds products, compares stored facts, retrieves policy evidence, mutates a reversible session cart, and prepares a checkout handoff. The human sees the same state, reviews normal WooCommerce checkout, supplies or verifies customer details, accepts terms, and explicitly places the no-charge demo order.

The agent never places an order, submits payment, accepts terms, cancels, or refunds. If a shopper asks for an unsupported back-in-stock notification, the agent records a redacted capability gap and clearly says that no notification was created.

Afterward, the merchant and agent work together on operations. They inspect the workflow timeline and business outcome, identify the slowest or failed tool, see unsupported demand, and apply a reversible policy change.

## Technical implementation

- WordPress 6.9+ native Abilities API with typed input/output schemas, execution callbacks, permission callbacks, and explicit first-party exposure.
- Framework-free browser runtime with top-level/API detection, awaited atomic registration, separate registration cleanup and client-side execution abort handling, credential refresh, stale-manifest recovery, cross-tab invalidation, compact outputs, and progressive enhancement. If server work has already started, its recorded success/failure remains authoritative even when the client stops waiting.
- Anonymous demo sessions use a 256-bit `HttpOnly` cookie, one-way stored digest, short-lived signed CSRF token, exact Origin checks, rate limits, size caps, and request-ID replay protection.
- Four portable WordPress tables store workflows, redacted events, one primary order attribution per rule version, and capability gaps. No raw prompts, identities, cookies, nonces, headers, addresses, or payment fields are recorded.
- WooCommerce APIs and HPOS-safe CRUD handle catalog, cart, order, status, and refund state. Revenue is counted only from paid orders and always shows gross, refunds, and net by currency.
- Public monitoring SQL is scoped to the current demo-session hash before rows are fetched. Persistent admin policy uses dedicated WordPress capabilities.
- GPL-2.0-or-later source, original fictional products/artwork, checksummed plugin ZIP, Docker reproduction, and Playground portability bundle.

## Impact

WordPress powers a large and diverse web. This challenge prototype proves a reusable plugin foundation for safe agent semantics and operational evidence without a separate agent backend or analytics SaaS. The submitted v0.1 public execution/analytics path is deliberately gated to the isolated demo environment; the normal-install admin currently provides persistent policy controls and diagnostics, while authenticated site-wide execution is a post-hackathon hardening step. The same open-source pattern can later serve booking, learning, membership, support, and other WordPress workflows.

## Links

- Live judge start: **[PRIMARY_DEMO_URL]**
- Storefront: **[STOREFRONT_URL]**
- Agent SNR: **[AGENTOPS_URL]**
- Readiness: **[HEALTH_URL]**
- Public repository: **[REPOSITORY_URL]**
- Frozen release and plugin ZIP: **[RELEASE_URL]**
- Playground portability demo: **[PLAYGROUND_URL]**
- Public video (<3 minutes): **[YOUTUBE_URL]**
