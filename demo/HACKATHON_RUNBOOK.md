# Agent SNR hackathon demo runbook

This is one continuous browser story: a shopper agent prepares a purchase, a person authorizes the order, and a merchant uses Agent SNR to inspect and govern exactly that browser session. Keep the storefront and Agent SNR tabs in the **same browser profile** so their private demo-session cookie—and therefore their evidence scope—stays aligned.

## 1. Preflight the isolated release

The showcase uses a checksummed plugin ZIP in `dist/`, not a mutable source bind mount. On a clean clone, `start` first builds the deterministic ZIP and checksum from the current checkout; that auto-build needs `rsync` and `zip`. A supplied prebuilt ZIP skips those build-only tools. The launcher then runs the verified artifact as the fixed Compose project `agent-snr-showcase` on local port `18084`, forces the HTTP binding to `127.0.0.1`, and leaves the ordinary development project and its volumes alone.

```bash
./bin/start-showcase.sh start
./bin/start-showcase.sh status
./bin/start-showcase.sh verify
```

If port `18084` is already occupied, choose another local port consistently for every showcase command:

```bash
export WMCP_SHOWCASE_PORT=18085
./bin/start-showcase.sh start
./bin/start-showcase.sh verify
```

Use only the local URLs printed by the launcher. Before the audience arrives, open the judge landing page, the storefront, Agent SNR, and the readiness page once. Confirm that the readiness page reports the WordPress/WooCommerce foundation and that the browser exposes Site Tools. Do not claim this localhost setup as a hosted HTTPS demo.

The walkthrough URLs below assume the default port `18084`. If you override it, use the corresponding URLs printed by the launcher.

For a completely new database, the intentionally destructive command is guarded by the exact showcase project name:

```bash
./bin/start-showcase.sh reset --confirm-agent-snr-showcase
```

That reset removes only the `agent-snr-showcase` containers, network, and named volumes. It does not touch the regular development project.

## 2. Start with a fresh private session

1. In the Site Tools-capable browser, open `http://localhost:18084/agentops-demo/`.
2. Select **Start fresh session**. This rotates only the current browser scope and clears its demo cart and scoped evidence.
3. Open `http://localhost:18084/storefront-demo/` in another tab in the same browser profile.
4. Keep both tabs open for the entire demo. Do not switch to a private window or another browser midway.

Say: “Agent SNR does not mix sample history into this screen. We are starting with one empty, private browser evidence window.”

## 3. Run the evidence-first shopper workflow

On the storefront, give the browser agent this prompt:

> Find a waterproof backpack under $120. Compare the two best in-stock choices using product evidence, confirm that the published return policy is at least 30 days, and add the best-value option to my cart. Do not start checkout.

Pause on the visible results and call out the evidence chain:

- search is constrained by stock, price, and product facts;
- comparison is structured, not invented from page prose;
- the return-policy answer cites the store’s published policy;
- cart mutation is reversible and visibly updates the shared cart badge.

Now ask for a capability that the store does not implement:

> Find the blue TerraRoll 25 Pack and notify me when it is back in stock. If the store cannot do that, say so honestly and record the unsupported request as a capability signal; do not pretend a notification was created.

Expected result: the agent reports the limitation, calls the capability-gap tool, and explicitly says that no alert, reservation, or order was created.

Now prepare the human boundary:

> Prepare checkout for the current cart and stop at the normal WooCommerce handoff. Do not navigate, submit customer data, accept terms, or place the order.

Expected result: checkout stops at a visible link for a person, and no order exists yet.

## 4. Cross the human checkpoint

Follow the prepared checkout link yourself. The demo checkout fields are fictional and prefilled. Select **Demo payment — no charge**, review the order, and press **Place order**.

Say: “The agent could search, compare, read policy, change a cart, and prepare checkout. It could not accept terms, submit a checkout, charge, cancel, or refund. The person just crossed that boundary.”

Keep the order-confirmation tab open long enough for the order number and no-charge result to be visible.

## 5. Replay the outcome in Agent SNR

Return to the public Agent SNR tab and give the merchant prompt:

> Monitor this browser session. Load the overview, list its agent workflows, explain the storefront workflow with its redacted timeline, show the funnel and tool health, connect the human order to its attribution and net outcome, and include the unsupported back-in-stock capability signal.

Use the visible **Agent sessions** row to open the workflow replay. Call out:

- tool calls, terminal outcomes, and latency are present;
- raw prompts, arbitrary payloads, identity, address, payment data, and screen video are absent;
- checkout handoff and the later WooCommerce order are separate facts;
- attribution is evidence-based; “Agent direct” is not the same thing as WooCommerce’s traffic-origin label;
- the unsupported request appears as opportunity context, never invented “lost revenue.”

## 6. Demonstrate session-only governance

With the storefront and Agent SNR tabs still open, ask on Agent SNR:

> Disable `compare_products` only for this demo session, with the reason “Hackathon session-control demonstration.”

Switch to the storefront tab and show that comparison is no longer available to the agent after the manifest refresh. Explain that the control cannot change site-wide policy or another browser’s tools.

Then restore it from Agent SNR:

> Clear my session override and restore `compare_products` for this demo session, with the reason “Hackathon demonstration complete.”

Return to the storefront and show that the tool is registered again. This closes the monitor → control → runtime loop without claiming a persistent public policy change.

## 7. Prove refunds reduce net outcome

This is an operator action, not a shopper-agent ability. Before the demo, the operator can privately retrieve the machine-generated local credentials with:

```bash
./bin/start-showcase.sh credentials
```

Never put those credentials in slides, chat, repository text, or the recording. In a separate operator tab in the **same browser profile**, sign in locally, open **WooCommerce → Orders**, choose the just-created order, and issue a full refund without attempting an external gateway refund.

Return to the public Agent SNR tab and ask:

> Refresh this session’s overview and explain the same storefront workflow again. Show gross attributed value, refund value, and net attributed value after the operator refund.

Expected result: the original paid-order evidence remains, refund value increases, and net attributed value falls accordingly. Say: “Agent SNR does not rewrite history; it appends verified WooCommerce outcome evidence.”

## 8. Close and stop

Close with: “This is the full loop: structured agent action, explicit human authority, verified business outcome, privacy-safe replay, and a control that changes the next agent run.”

Stop the isolated showcase without deleting its data:

```bash
./bin/start-showcase.sh stop
```

Use `start` to resume the same showcase data later, or the guarded reset command when a truly clean database is required.
