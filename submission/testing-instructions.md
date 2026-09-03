# Judge testing instructions

This repository file includes owner preflight material and the finished Devpost field copy. Paste **only** the section headed **Devpost Testing Instructions — paste from here** after replacing its URL tokens; repository-relative links above that marker are not part of the form copy.

## Release-owner preflight for Devpost

Complete these against one exact frozen release before placing the judge-start URL in Devpost:

1. Recheck the [Official Rules](https://webmcp.devpost.com/rules) and complete [`devpost-rules-checklist.md`](devpost-rules-checklist.md).
2. Deploy the exact build from the frozen public-repository commit to stable, top-level HTTPS hosting and confirm every submitted URL works logged out, free of charge, with no local-build or iframe-only dependency.
3. Test the storefront and Agent SNR pages through at least one official judge path: the latest ChatGPT desktop in-app browser or Chrome 149+ with WebMCP testing enabled. Confirm the expected 12 and 8 tool catalogs and the complete judge path below.
4. Verify the public repository contains the frozen source/assets/instructions and visible root license, and record the public commit. If using the project's optional tag/checksum provenance, verify those values too.
5. Publish the narrated demo publicly on YouTube, verify its processed duration is below 3:00, and audit every frame/audio element for rights, privacy, credentials, and unrelated marks.
6. Confirm all linked evidence excludes keys, cookies, session material, customer data, private logs, and mutable placeholder URLs.

The demo must remain available without charge or restriction through September 21, 2026 at 5:00 p.m. PT. If authentication is added, place working credentials only in Devpost's private Testing Instructions field.

## Additional quality evidence

These are useful project gates but are not separate Devpost submission requirements:

- The renamed package's deterministic, native Chrome, and protected model-backed eval gates are complete. The protected suites pass all three strict provenance checks at rename commit `410c198`; see [`webmcp-eval-report-2026-09-01.md`](webmcp-eval-report-2026-09-01.md).
- [`workbench-validation.md`](workbench-validation.md) provides an optional deep browser-extension audit and replay sheet.
- [`webmcp-directory-listing.md`](webmcp-directory-listing.md) provides optional scanner and public-directory evidence.

## Devpost Testing Instructions — paste from here

1. Open **[PRIMARY_DEMO_URL]** as a top-level page in the latest ChatGPT desktop in-app browser, or Chrome 149+ with `chrome://flags/#enable-webmcp-testing` enabled.
2. Confirm the readiness card detects the browser, then click **Open storefront**. Tool registration occurs on that top-level storefront surface, where the shared workflow UI is present.
3. Confirm **API detected** becomes **Tools ready**. Ask the agent to start with the site's Agent Guide; confirm the visible guide changes from **Start here** to **Read by agent** without advancing the commerce journey.
4. Search for an in-stock waterproof backpack under $100 with `IPX5`. Confirm there are zero matches and a **Site observed** opportunity is recorded without any feedback call.
5. Relax only the water rating. Confirm RainTrail and HarborLite appear, both show IPX4, then compare them, verify the published returns evidence, add HarborLite, and prepare checkout.
6. Ask the agent to follow the guide's feedback instructions for the constraint. Confirm the receipt says **Agent reported**, evidence is linked, site measurements show two eligible products and highest IPX4, and checkout/order metrics are pending rather than zero.
7. Confirm no order exists yet. Click the visible checkout link, review the fictional prefilled details, choose **Demo payment — no charge**, and have the human click **Place order**.
8. Open **[AGENTSNR_URL]** (Agent SNR) and run the merchant prompt. Confirm Agent Sessions, Workflow Replay, Opportunity Signals, tool health, the paid order, attribution evidence, gross/refund/net, and separate Site observed / Agent reported / Site verified labels are visible.
9. Ask: “Disable product comparison for this demo session.” Confirm comparison disappears/fails immediately while a separate private browser session is unchanged.
10. Use **Reset my demo** to rotate to a clean private scope.

No login is required for the judged path. The demo processes no payment and sends no email. WordPress Playground is provided as a portability sandbox; use the primary top-level HTTPS demo for official in-app-browser or Chrome judging.

## End of Devpost paste section
