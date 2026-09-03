# Agent SNR demo evidence

The files under `submission/demo-screenshots/` are original 1440×900 captures from one isolated, exact-release local rehearsal. The matching guide, signal, feedback, workflow, product, order, measurement, and console evidence is recorded in `showcase-summary.json`.

The September 3 post-rename recapture used plugin ZIP SHA-256 `514a7f86fe4fadb0d3786ded3a58017a4be0c26546f5925533bd8e1d31a58943`; `showcase-summary.json` has SHA-256 `899c2a8c898b4e66dc2678aac3459fa6b6fe1d6cf1451b8864b8e19292d013a1`. The capture completed through `/agentsnr-demo/` with zero console errors. Visual and OCR review confirmed the generic **Agent SNR Demo Store** title, the **Agent SNR / Demo Store** storefront lockup, and the final one-order / `$69.00` refund / `$0.00` net outcome evidence.

1. `01-agent-snr-overview.png` — public readiness and product overview.
2. `02-agent-guide.png` — Agent Guide 1.1 with read state, co-browsing scope, seven shopper steps, zero sensitive tools, human/pricing/privacy boundaries, triggers, and optional two-report policy.
3. `03-zero-result-opportunity.png` — the IPX5-under-$100 search with zero results and a server-confirmed Site observed signal.
4. `04-agent-feedback-handoff.png` — HarborLite handoff plus Agent reported feedback, linked site evidence, verified product metrics, and pending order metrics.
5. `05-human-order-confirmation.png` — real `$69.00` WooCommerce order using the no-charge demo gateway.
6. `06-agent-snr-monitor.png` — same-session workflow, tool, opportunity, commerce, and attribution overview.
7. `07-opportunity-signals.png` — separate Site observed, Agent reported, and Site verified provenance with product-coverage action.
8. `08-workflow-replay.png` — redacted ordered workflow timeline with two observed signals, one linked feedback report, and direct attribution.
9. `09-session-controls.png` — comparison disabled only for the current demo session while guide and feedback remain available.
10. `10-refund-net-outcome.png` — full `$69.00` refund retained as outcome evidence and net attributed value reduced to `$0.00`.

Regenerate the complete set atomically after starting the isolated showcase:

```bash
./bin/start-showcase.sh start
npx playwright install chromium
npm run showcase:capture
```

The capture uses fictional products, `.invalid` customer data, and the local no-charge payment method. It creates and fully refunds one local WooCommerce order and records a session-only comparison restriction inside its disposable browser scope. Remote capture requires HTTPS, an explicit opt-in, and explicit credentials. Re-capture the sequence from the frozen hosted HTTPS release before final Devpost upload so the public URL and deployed provenance match the submitted tag.
