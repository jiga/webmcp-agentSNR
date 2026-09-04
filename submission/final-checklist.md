# Final submission checklist

Checked items are complete in the prepared repository. Unchecked items require the entrant's accounts, public infrastructure, formal trademark/domain clearance, or media; items explicitly labeled optional or recommended are non-blocking.

The rule-by-rule source of truth is [`devpost-rules-checklist.md`](devpost-rules-checklist.md). The entry is not ready to submit until every hard blocker and entrant declaration there is complete. The live Devpost overview now displays an extended deadline of **September 4, 2026 at 1:00 a.m. PDT**; retain evidence because the earlier rules copy still shows the original deadline.

## Code and artifacts

Checked exact-artifact items in this section describe the current renamed package. Protected model-backed results are bound to rename commit `410c198963ec649ed58e21fce7c80103db3d0ad8`. Documentation-only publication records do not change artifact bytes; published release-artifact parity remains an explicitly recommended, unchecked item below if a release artifact is linked.

- [x] The renamed package's automated JavaScript, PHP, security, browser, WooCommerce, HPOS-on/off, and artifact tests pass from a clean checkout; current submission-copy static checks also pass.
- [x] The renamed exact ZIP passes WordPress 6.9 / WooCommerce 10.9.4, WordPress 7.0.4 / WooCommerce 11.0.1, and WordPress 7.1 / WooCommerce 11.0.1 HPOS.
- [ ] Optional newest-release evidence: re-run the matrix against WooCommerce 11.1 if a stable build is available and time permits before final deployment; the declared 10.9.4/11.0.1 range already passes.
- [x] Official Plugin Check reports zero errors against the extracted renamed ZIP; reviewed warnings are recorded in `verification-report.md`.
- [x] The renamed ZIP contains one plugin root, local runtime assets, license/notices, and no development/private files.
- [x] The renamed Playground bundle validates, installs, activates, seeds, and reports its iframe limitation accurately.
- [x] Docker one-command bootstrap succeeds with a new named volume.
- [x] Isolated `agent-snr-showcase` rehearsal runs the verified release ZIP without touching development volumes.
- [x] Editable 12-slide architecture/demo presentation and ten real exact-artifact local-flow reference screenshots are included.
- [x] The public repository is anonymously accessible at `https://github.com/jiga/webmcp-agentSNR`, with `main` as the default branch and the full unsquashed history preserved.
- [ ] Recommended project provenance—not a Devpost requirement: release tag, ZIP checksum, hosted plugin version, and repository commit match. Do not delay submission past the deadline for optional release automation.

## WebMCP quality and external evidence

- [x] The pinned GoogleChromeLabs WebMCP Evals keyless smoke passes 5/5 storefront and 6/6 Agent SNR calls on the renamed exact ZIP, including application-error-envelope enforcement.
- [x] Protected model-backed selection and browser evals pass all three strict provenance checks against rename commit `410c198963ec649ed58e21fce7c80103db3d0ad8`: storefront 54/54, Agent SNR 45/45, and the live browser journey 8/8 with zero console errors and no new order; see [`webmcp-eval-report-2026-09-01.md`](webmcp-eval-report-2026-09-01.md).
- [x] The ChatGPT desktop in-app browser discovers exactly 12 storefront and 8 Agent SNR canonical tools on the frozen top-level HTTPS pages; safe guide/context/overview/diagnostic/workflow calls succeed.
- [ ] Optional quality evidence: the owner completed [`workbench-validation.md`](workbench-validation.md) with the actual installed version, exact release identity, manual calls, Audit findings, saved-call replay, repeated evals, approval/log review, and User Mode evidence.
- [x] Provider keys and raw `.evals/` reports remain private and ignored; no Workbench log is included, and every linked excerpt or screenshot is sanitized.
- [ ] Optional quality evidence: the WebMCP.com public scanner passes both frozen pages with expected catalogs and no API/load/blocking or unexplained Sensitive Action result.
- [ ] Optional discovery evidence: the human directory request is approved, post-index API lookups return `supported: true` for the intended public representation, and the final directory URL is recorded using [`webmcp-directory-listing.md`](webmcp-directory-listing.md).

## Judged workflow

- [x] Both top-level HTTPS pages register their current imperative catalogs in the ChatGPT desktop in-app browser.
- [x] Site remains human-usable with WebMCP absent.
- [x] Shopper prompt visibly completes search, compare, policy, and cart.
- [x] Agent discovers the guide; a zero-result search records a site-observed opportunity without feedback.
- [x] Structured agent feedback links only same-workflow evidence and displays site-computed metrics separately.
- [x] Checkout handoff creates no order; human demo checkout creates and pays a real Woo order.
- [x] Order, product evidence, workflow, attribution class, gross/refund/net, and currency correlate correctly.
- [x] Merchant prompt returns scoped overview, funnel, explanation, health, and Signals with distinct Site observed / Agent reported / Site verified provenance.
- [x] Session policy disables comparison server-side and refreshes browser tools without cross-session effects.
- [x] Reset creates a clean scope without deleting another judge’s records.

## Security and operations

- [x] Cross-origin/CSRF/replay/schema/size/rate/evidence/capability/session-isolation tests pass.
- [x] Redaction/secret checks are clean; raw prompt/search/free-form feedback is absent and cacheable page HTML contains no session or credential values.
- [x] Demo gateway is absent outside explicit demo mode and outbound demo email is disabled in the demo configuration.
- [ ] Recommended reliability evidence: monitoring and backups are active, and a restore has been rehearsed.
- [x] Root, storefront, Agent SNR, readiness, REST health, catalog, and WordPress page routes work without authentication after two deploys.
- [ ] Keep the paid Render services and unrestricted public access available through the required judging window.

## Devpost and media

- [x] Agent SNR appears consistently as the public product name.
- [ ] Entrant has joined the challenge and personally confirmed age, supported jurisdiction, no exclusion/conflict, entry type, team roster, and authorized Representative where applicable.
- [ ] Entrant personally confirms originality, sole ownership/contributor rights, third-party authorizations, no prohibited Sponsor/Administrator support, no malicious code, and accuracy of every claim.
- [ ] Formal trademark and domain clearance for Agent SNR is complete.
- [x] The former public demo-store candidate name is removed; site title, storefront lockup, policy copy, launchers, and reference captures use generic Agent SNR demo-store wording.
- [ ] The Agent SNR name, `webmcp-agentSNR` repository slug, and `wmcp-agentsnr` plugin slug have completed formal trademark/domain screening.
- [x] Description covers WebMCP fit, UX improvement, human-agent collaboration, and implementation.
- [x] Local reference screenshots contain only fictional demo data and original project assets.
- [x] Public source/license, live URL, and testing instructions are complete; public judging requires no credentials.
- [ ] Public YouTube video has audio, shows actual WebMCP usage, and is under three minutes.
- [x] `submission/agent-snr-devpost-thumbnail.png` accurately represents Agent SNR's signal-to-noise workflow, is exact 3:2, and contains no secret, private data, screenshot, or third-party logo.
- [ ] Recommended gallery evidence: if gallery images are included, use frozen hosted-release captures that contain no secret, private data, or unapproved asset.
- [x] GitHub detects GPL-2.0 from the root license; timestamped history is unsquashed, and anonymous repository/API/raw README requests return HTTP 200.
- [ ] Every link is verified from a logged-out external device.
- [ ] Required Devpost name, tagline, thumbnail, story, Built With tags, Try It Out URL, YouTube link, public-repository URL, team, country/category/custom answers, and terms checkbox are complete in English.
- [ ] Recommended gallery images, if used, are uploaded, legible, rights-cleared, and consistent with the frozen release.
- [ ] Submission is accepted before the live Devpost deadline of September 4, 2026 at 1:00 a.m. PDT; the extension display and receipt are saved and all submitted resources are frozen immediately.
- [ ] Live project remains free and unrestricted through September 21, 2026 at 5:00 p.m. PT; entrant-controlled repo/deployed code and configuration/seed content/video remain unchanged until winner announcement, while normal isolated judge-session data and bounded cleanup continue as designed; later work stays in a fork.
