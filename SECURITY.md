# Security policy

## Supported release

Security fixes are prepared for the current `0.1.x` hackathon release line. The hosted build, submitted repository, video, Devpost entry, and any submitted tag must remain frozen until the winner announcement; any necessary security disclosure during that window should be handled privately and coordinated with the challenge organizer before changing submitted resources.

## Reporting a vulnerability

Do not include secrets, personal data, or exploit details in a public issue. As recommended public-launch hygiene—not a Devpost submission requirement—the owner should configure a private security-reporting channel (for example, private repository security advisories) and replace this paragraph with the exact contact path.

Include:

- affected version and environment;
- the smallest reproducible request or workflow;
- expected and observed authorization boundary;
- impact and whether another demo session is affected;
- request/event IDs, but never cookies, CSRF tokens, nonces, addresses, or payment data.

## Security boundaries

- WebMCP annotations are hints, never authorization.
- Only first-party catalog entries are exposable; third-party Abilities are not auto-published.
- Public WebMCP discovery is scoped to top-level co-browsing: 12 storefront and 8 Agent SNR tools. Two legacy gap abilities remain registered with `wmcp.public=false` and are not included in manifests.
- The catalog contains zero Sensitive Action tools. `prepare_checkout_handoff` reveals normal checkout but cannot navigate, submit customer data, accept terms, create or change an order, or process money.
- Anonymous execution is available only in explicit server-side demo mode and is bound to one opaque browser session.
- Persistent policies and site-wide analytics require dedicated WordPress capabilities and REST nonces.
- Public session overrides may disable a storefront tool or clear that local override. They cannot re-enable a site-disabled tool or affect another session.
- Server policy is authoritative even if a browser retains a stale registration.
- The demo payment gateway has no payment fields, makes no network payment call, and cannot register outside explicit demo mode.
- Order and refund access uses WooCommerce CRUD APIs for HPOS compatibility.

## Data minimization

The workflow ledger stores stable event names, tool/version/risk, outcome, duration, carefully allowlisted commerce facts, canonical demand signatures, structured feedback enums, evidence IDs, and one-way session/actor hashes. It does not store raw prompts or search text, conversations, free-form feedback, user identities, email, phone, addresses, cookies, nonces, authentication headers, payment fields, full request/response payloads, or product reviews.

Agent feedback is untrusted testimony. The caller cannot choose its trust label, scope, status, or metric values; every referenced event must belong to the same storefront workflow and private demo session. Site-observed signals and server-computed measurements are rendered separately, and feedback never changes attribution, catalog, inventory, or policy.

Demo session expiry is enforced on request. WP-Cron performs bounded garbage collection only; it is not relied upon for authorization expiry.

## Deployment requirements

- Serve the primary demo over HTTPS as a top-level page.
- Apply `Origin-Agent-Cluster: ?1`, `Permissions-Policy: tools=(self)`, `Referrer-Policy: strict-origin-when-cross-origin`, and `X-Content-Type-Options: nosniff` consistently.
- Keep session/manifest/execution/analytics responses `private, no-store` and prevent full-page caches from embedding session state.
- Store environment secrets outside Git; rotate demo/admin credentials before public deployment.
- Run the pinned native WebMCP smoke only against disposable loopback environments. Its runner refuses provider API keys; keep model-backed reports and Workbench logs private and sanitized.
- Disable or sink outbound demo email and monitor PHP fatals and public health checks.
- Back up before the submission freeze and verify all routes while logged out in an isolated browser profile.
