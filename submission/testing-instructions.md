# Judge testing instructions

1. Open **[PRIMARY_DEMO_URL]** as a top-level page in the latest ChatGPT desktop app with Site Tools enabled, or Chrome 149+ with the WebMCP testing flag. Current ChatGPT Site Tools require a Work or Codex workspace and GPT-5.6 Sol or Terra.
2. Confirm the readiness card detects the browser, then click **Open storefront**. Tool registration occurs on that top-level storefront surface, where the shared workflow UI is present.
3. Confirm **API detected** becomes **Tools ready**, copy/run the shopper prompt, and verify product results, two-product comparison, published return-policy evidence, workflow rail, and cart all update visibly.
4. Ask: “Find the blue TerraRoll 25 Pack and notify me when it is back in stock.” Confirm the response links the out-of-stock product to a capability gap and says no notification was created.
5. Run checkout handoff. Confirm no order is created yet. Click the visible checkout link, review the fictional prefilled details, choose **Demo payment — no charge**, and have the human click **Place order**.
6. Open **[AGENTOPS_URL]** (Agent SNR) and run the merchant prompt. Confirm Agent Sessions, Workflow Replay, Signals, tool health, journey, capability gap, paid order, attribution evidence, gross revenue, refund total, and net revenue are visible.
7. Ask: “Disable product comparison for this demo session.” Confirm comparison disappears/fails immediately while a separate private browser session is unchanged.
8. Use **Reset my demo** to rotate to a clean private scope.

No login is required for the judged path. The demo processes no payment and sends no email. WordPress Playground is provided for portability, but current ChatGPT does not discover tools inside its iframe; use the primary top-level demo for Site Tools judging.
