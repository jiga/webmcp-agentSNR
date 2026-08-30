# Agent SNR demo evidence

The files under `submission/demo-screenshots/` are original 1440×900 captures from one isolated, exact-release local rehearsal. The matching identifiers and console result are recorded in `showcase-summary.json`.

1. `01-agent-snr-overview.png` — public readiness and product overview.
2. `02-storefront-evidence.png` — grounded product results and completed workflow rail.
3. `03-human-order-confirmation.png` — real WooCommerce order using the no-charge demo gateway.
4. `04-agent-snr-monitor.png` — same-session workflow, tool, capability, and commerce evidence.
5. `05-workflow-replay.png` — redacted ordered workflow timeline and direct attribution.
6. `06-session-controls.png` — comparison disabled only for the current demo session.
7. `07-refund-net-outcome.png` — full refund reflected as `$109.00` refund and `$0.00` net.

Regenerate the complete set atomically after starting the isolated showcase:

```bash
./bin/start-showcase.sh start
npx playwright install chromium
npm run showcase:capture
```

The capture uses fictional products, `.invalid` customer data, and the local no-charge payment method. It creates and fully refunds one local WooCommerce order and records a session-only comparison restriction inside its disposable browser scope. Remote capture requires HTTPS, an explicit opt-in, and explicit credentials. Re-capture the sequence from the frozen hosted HTTPS release before final Devpost upload so the public URL and deployed provenance match the submitted tag.
