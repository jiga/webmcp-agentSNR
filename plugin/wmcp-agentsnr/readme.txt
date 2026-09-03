=== Agent SNR ===
Tags: webmcp, woocommerce, monitoring, analytics, ai
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Agent outcome monitoring for WordPress: expose safe WebMCP workflows, capture missed opportunities and agent feedback, measure verified outcomes, and govern the tool layer locally.

== Description ==

Agent SNR is an open-source, local-first outcome-monitoring layer for browser agents on WordPress. It registers narrowly scoped top-level WebMCP tools backed by WordPress Abilities, publishes a friendly Agent Guide, reconstructs privacy-safe workflow replays, separates site-observed opportunities from structured agent feedback and site-verified measurements, links same-session WooCommerce orders and refunds, and provides merchant controls.

Workflow Replay is a redacted event-sourced tool and commerce timeline. It does not record or reconstruct the DOM, screen, video, raw prompt, or arbitrary payload.

The agent can research products, compare stored facts, show published policy evidence, and prepare a reversible cart and checkout handoff. A human reviews normal WooCommerce checkout and places the order. The agent never accepts terms, submits payment, places, cancels, or refunds an order.

The bundled public execution and analytics path requires an explicit server-side demo constant. Normal installs begin disabled and provide persistent policy/diagnostic administration; authenticated site-wide WebMCP analytics execution is not included in v0.1. Analytics are retained on uninstall unless the administrator opts into deletion.

== Installation ==

1. Upload the `wmcp-agentsnr` ZIP under Plugins > Add New > Upload Plugin.
2. Activate on WordPress 6.9+ with PHP 8.1+.
3. Review the Agent SNR settings before enabling any tool.
4. WooCommerce is optional; commerce tools appear only while it is active.
5. Serve public WebMCP pages over top-level HTTPS with the documented origin and permissions headers.

== Frequently Asked Questions ==

= Does the agent place orders or process payments? =

No. Checkout handoff returns the normal WooCommerce checkout URL. A human reviews the order and explicitly places it.

= Does the Agent SNR ledger duplicate conversations or checkout data? =

No. The Agent SNR workflow ledger contains allowlisted operational events, canonical demand signatures, structured feedback enums, and one-way session hashes; it does not duplicate raw prompts or search text, conversations, free-form feedback, identity, address, cookie, nonce, authorization, or payment fields. Ordinary WooCommerce remains the system of record for order details a human submits at checkout.

= Does WebMCP work inside WordPress Playground? =

Playground is normally embedded in an iframe. Use the normal top-level HTTPS site for the official ChatGPT in-app-browser or Chrome judging path; Playground is a portability sandbox.

== Changelog ==

= 0.1.0 =

* Initial hackathon release.
