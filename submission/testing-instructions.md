# Judge testing instructions

1. Open **[PRIMARY_DEMO_URL]** as a top-level page in the latest ChatGPT desktop app with Site Tools enabled, or Chrome 149+ with the WebMCP testing flag. Current ChatGPT Site Tools require a Work or Codex workspace and GPT-5.6 Sol or Terra.
2. Confirm the readiness card detects the browser, then click **Open storefront**. Tool registration occurs on that top-level storefront surface, where the shared workflow UI is present.
3. Confirm **API detected** becomes **Tools ready**. Ask the agent to start with the site's Agent Guide; confirm the visible guide changes from **Published** to **Read by agent** without advancing the commerce journey.
4. Search for an in-stock waterproof backpack under $100 with `IPX5`. Confirm there are zero matches and a **Site observed** opportunity is recorded without any feedback call.
5. Relax only the water rating. Confirm RainTrail and HarborLite appear, both show IPX4, then compare them, verify the published returns evidence, add HarborLite, and prepare checkout.
6. Ask the agent to follow the guide's feedback instructions for the constraint. Confirm the receipt says **Agent reported**, evidence is linked, site measurements show two eligible products and highest IPX4, and checkout/order metrics are pending rather than zero.
7. Confirm no order exists yet. Click the visible checkout link, review the fictional prefilled details, choose **Demo payment — no charge**, and have the human click **Place order**.
8. Open **[AGENTOPS_URL]** (Agent SNR) and run the merchant prompt. Confirm Agent Sessions, Workflow Replay, Opportunity Signals, tool health, the paid order, attribution evidence, gross/refund/net, and separate Site observed / Agent reported / Site verified labels are visible.
9. Ask: “Disable product comparison for this demo session.” Confirm comparison disappears/fails immediately while a separate private browser session is unchanged.
10. Use **Reset my demo** to rotate to a clean private scope.

No login is required for the judged path. The demo processes no payment and sends no email. WordPress Playground is provided for portability, but current ChatGPT does not discover tools inside its iframe; use the primary top-level demo for Site Tools judging.
