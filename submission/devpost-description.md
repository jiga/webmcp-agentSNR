# Agent SNR — Devpost submission copy

## Submission form values

- **Project name:** Agent SNR
- **Tagline (118 characters):** See what website agents did, hear what they experienced, and connect WordPress journeys to verified business outcomes.
- **Built With:** WebMCP, WordPress, WooCommerce, PHP, JavaScript, REST API, Docker, Playwright, Chrome
- **Thumbnail candidate:** `demo-screenshots/01-agent-snr-overview.png`

Replace every bracketed link only after the public repository, hosted demo, and YouTube video are frozen and verified logged out. If linking an optional tagged release, verify and freeze it too. The entrant must complete name/trademark and rights clearance before using this copy in the final form.

## Project story — paste from here

## One-line pitch

**Agent outcome monitoring for WordPress.** Browser agents discover how to use a real WooCommerce storefront, while merchants see what agents did, hear structured feedback about the experience, discover missed demand, connect human handoffs to verified outcomes, and govern the WebMCP tool layer.

## The problem

Most WordPress agent integrations stop at exposing actions. A merchant can see that a tool was called, but not the complete path from shopper goal to product evidence, cart, human checkout, paid order, refund, failure, or unsupported request. Raw activity logs also make it easy to overclaim revenue and difficult to answer a practical question: what should the site operator improve or disable next?

## The solution

**Agent SNR** closes that loop locally inside WordPress. SNR retains its established meaning—**signal-to-noise ratio**—because the product separates verified business and reliability evidence from raw, unactionable agent noise:

1. A top-level storefront publishes a friendly Agent Guide and narrow WebMCP tools for product context, discovery, comparison, published policy evidence, reversible cart changes, checkout handoff, and feedback.
2. A redacted workflow ledger records one start and one terminal outcome for every authenticated tool request.
3. The site automatically records zero-result and constrained-search opportunities even when an agent says nothing. Structured agent feedback is stored separately as testimony, with only same-workflow evidence and server-computed measurements.
4. WooCommerce order and refund hooks preserve same-session product evidence and classify eligible agent-linked orders as direct, assisted, or influenced without double-counting revenue. Orders without qualifying evidence are excluded from attributed reporting rather than claimed.
5. A separate top-level Agent SNR surface exposes tools for the journey, Workflow Replay, unified Opportunity Signals, tool health, attributed revenue, diagnostics, and a restrictive session-only policy change.

The two outcome-oriented pages publicly discover 12 storefront tools and 8 Agent SNR tools. Two older capability-gap abilities remain registered only for server-side compatibility and are intentionally absent from WebMCP discovery, leaving one canonical public tool per intent.

The memorable loop is not “AI shopping.” It is a website observing and improving its own agent interface.

Workflow Replay is a privacy-safe event timeline, not DOM capture, video recording, or pixel reconstruction.

## Why this use case is a strong fit for WebMCP

The workflows belong to the website. Prices, stock, product facts, policies, the current WooCommerce cart, WordPress permissions, and the human checkout UI already live there. WebMCP lets the page describe those capabilities directly to the browser agent instead of requiring brittle visual automation or a separate remote agent backend.

The submission uses the current imperative API, `document.modelContext.registerTool()`, in the top-level document. A dynamic manifest is generated from the same first-party registry used to register WordPress Abilities. The browser sends structured inputs to a same-origin WordPress REST execution gateway and displays every meaningful result in shared human-visible state.

WebMCP also makes the governance demonstration possible: the merchant asks Agent SNR to disable product comparison for this demo session. The server applies the restriction immediately, invalidates the manifest, and the browser replaces the prior registration set. Another judge’s session is unaffected.

## How it creates a better user experience

The agent does not guess through a visual storefront or hide work in an invisible backend. It receives typed, site-owned capabilities; the shopper sees the same search, comparison, policy, cart, and handoff state; and failures return bounded recovery guidance. The merchant gets a replayable explanation of what happened without collecting the conversation or exposing customer data. This reduces brittle UI automation for the agent, repetitive research for the shopper, and unstructured log investigation for the operator.

## What people and agents can do together that was difficult before

The agent first reads the site's versioned guide, then does the repetitive research and preparation: it finds products, compares stored facts, retrieves policy evidence, mutates a reversible session cart, prepares a checkout handoff, and can report structured friction. The human sees the same state, reviews normal WooCommerce checkout, supplies or verifies customer details, accepts terms, and explicitly places the no-charge demo order.

The agent never places an order, submits payment, accepts terms, cancels, or refunds. Feedback never changes catalog, inventory, policy, or attribution. Current journeys use automatic opportunity observations plus evidence-linked feedback; a legacy capability-gap ability remains server-side for compatibility but is not publicly discoverable.

Afterward, the merchant and agent work together on operations. They inspect the workflow timeline and business outcome, distinguish **Site observed**, **Agent reported**, and **Site verified** evidence, see missed demand or unsupported capabilities, and apply a reversible policy change.

## How WebMCP is implemented

- WordPress 6.9+ native Abilities API with typed input/output schemas, execution callbacks, permission callbacks, and explicit first-party exposure.
- Framework-free browser runtime with top-level/API detection, awaited atomic registration, separate registration cleanup and client-side execution abort handling, credential refresh, stale-manifest recovery, cross-tab invalidation, compact outputs, and progressive enhancement. If server work has already started, its recorded success/failure remains authoritative even when the client stops waiting.
- Anonymous demo sessions use a 256-bit `HttpOnly` cookie, one-way stored digest, short-lived signed CSRF token, exact Origin checks, rate limits, size caps, and request-ID replay protection.
- Four portable WordPress tables store workflows, redacted events, one primary order attribution per rule version, and unified opportunity/feedback signals. No raw prompts or search text, free-form feedback, identities, cookies, nonces, headers, addresses, or payment fields are recorded.
- Agents submit metric names, never values. Eligible product count, highest matching water rating, refinements, handoff, and order/value state are computed from site evidence; unavailable outcome metrics stay pending.
- WooCommerce APIs and HPOS-safe CRUD handle catalog, cart, order, status, and refund state. Revenue is counted only from paid orders and always shows gross, refunds, and net by currency.
- Public monitoring SQL is scoped to the current demo-session hash before rows are fetched. Persistent admin policy uses dedicated WordPress capabilities.
- GPL-2.0-or-later source, original fictional products/artwork, checksummed plugin ZIP, Docker reproduction, and Playground portability bundle.
- Pinned `webmcp-evals@0.0.4` schemas and natural-language fixtures, a keyless native Chrome smoke adapter that fails on application error envelopes, and provenance-bound model reports: 54/54 storefront selections, 45/45 Agent SNR selections, and an 8/8 live browser journey pass. Real-client validation remains a required release-owner gate; Workbench, scanner, and directory evidence are optional additional checks.

## Impact, creativity, and ambition

WordPress powers a large and diverse web. This challenge prototype proves a reusable plugin foundation for safe agent semantics and operational evidence without a separate agent backend or analytics SaaS. The submitted v0.1 public execution/analytics path is deliberately gated to the isolated demo environment; the normal-install admin currently provides persistent policy controls and diagnostics, while authenticated site-wide execution is a post-hackathon hardening step. The same open-source pattern can later serve booking, learning, membership, support, and other WordPress workflows.

The creative leap is the quality loop: the website teaches agents how to use it, observes unmet demand even when they provide no feedback, accepts bounded testimony when they do, verifies the business outcome from first-party evidence, and lets the operator govern the next journey through WebMCP itself. The complete executable implementation and original demo assets were created during the challenge period; timestamped evidence is documented in `HACKATHON.md` in the public repository.

## Links

- Live judge start: **[PRIMARY_DEMO_URL]**
- Storefront: **[STOREFRONT_URL]**
- Agent SNR: **[AGENTOPS_URL]**
- Readiness: **[HEALTH_URL]**
- Public repository: **[REPOSITORY_URL]**
- Optional frozen release and plugin ZIP, if published: **[RELEASE_URL]**
- Optional Playground portability demo, if included: **[PLAYGROUND_URL]**
- Public video (<3 minutes): **[YOUTUBE_URL]**
