# Final submission checklist

Checked items are complete in the prepared repository. Unchecked items require the entrant's accounts, public infrastructure, formal trademark/domain clearance, or media; items explicitly labeled optional or recommended are non-blocking.

The rule-by-rule source of truth is [`devpost-rules-checklist.md`](devpost-rules-checklist.md). The entry is not ready to submit until every hard blocker and entrant declaration there is complete. Deadline: **September 3, 2026 at 1:00 p.m. PDT / 20:00 UTC**.

## Code and artifacts

Checked exact-artifact items in this section describe the fully exercised engineering candidate at commit `e4d9c86b2754c735094b1dc8437fbd007d3e557a`. Compliance copy and owner metadata change future artifact bytes, so published-artifact parity remains an explicitly recommended, unchecked item below if a release artifact is linked.

- [x] The engineering candidate's automated JavaScript, PHP, security, browser, WooCommerce, HPOS-on/off, and artifact tests pass from a clean checkout; current submission-copy static checks also pass.
- [x] The engineering-candidate ZIP passes WordPress 6.9 / WooCommerce 10.9.4, WordPress 7.0.4 / WooCommerce 11.0.1, and WordPress 7.1 / WooCommerce 11.0.1 HPOS.
- [ ] Optional newest-release evidence: re-run the matrix against WooCommerce 11.1 if a stable build is available and time permits before final deployment; the declared 10.9.4/11.0.1 range already passes.
- [x] Official Plugin Check reports zero errors against the extracted engineering-candidate ZIP; reviewed warnings are recorded in `verification-report.md`.
- [x] The engineering-candidate ZIP contains one plugin root, local runtime assets, license/notices, and no development/private files.
- [x] The engineering-candidate Playground bundle validates, installs, activates, seeds, and reports its iframe limitation accurately.
- [x] Docker one-command bootstrap succeeds with a new named volume.
- [x] Isolated `agent-snr-showcase` rehearsal runs the verified release ZIP without touching development volumes.
- [x] Editable 12-slide architecture/demo presentation and ten real exact-artifact local-flow reference screenshots are included.
- [ ] Recommended project provenance—not a Devpost requirement: release tag, ZIP checksum, hosted plugin version, and repository commit match. Do not delay submission past the deadline for optional release automation.

## WebMCP quality and external evidence

- [x] The pinned GoogleChromeLabs WebMCP Evals keyless smoke passes 5/5 storefront and 6/6 Agent SNR calls on the exact engineering-candidate ZIP at commit `e4d9c86b2754c735094b1dc8437fbd007d3e557a`, including application-error-envelope enforcement.
- [x] Protected model-backed selection and browser evals pass the strict all-pass gate at commit `e4d9c86b2754c735094b1dc8437fbd007d3e557a`: storefront 54/54, Agent SNR 45/45, and the live browser journey 8/8 with zero console errors and no new order; see [`webmcp-eval-report-2026-09-01.md`](webmcp-eval-report-2026-09-01.md).
- [ ] At least one official judge path—the latest ChatGPT desktop in-app browser or Chrome 149+ with the WebMCP flag—discovers exactly 12 storefront and 8 Agent SNR canonical tools on the frozen top-level HTTPS pages.
- [ ] Optional quality evidence: the owner completed [`workbench-validation.md`](workbench-validation.md) with the actual installed version, exact release identity, manual calls, Audit findings, saved-call replay, repeated evals, approval/log review, and User Mode evidence.
- [ ] Provider keys and raw `.evals/`/Workbench logs remain private; every linked excerpt or screenshot is sanitized.
- [ ] Optional quality evidence: the WebMCP.com public scanner passes both frozen pages with expected catalogs and no API/load/blocking or unexplained Sensitive Action result.
- [ ] Optional discovery evidence: the human directory request is approved, post-index API lookups return `supported: true` for the intended public representation, and the final directory URL is recorded using [`webmcp-directory-listing.md`](webmcp-directory-listing.md).

## Judged workflow

- [ ] Both top-level HTTPS pages register their current imperative catalogs in at least one official judge path: the latest ChatGPT desktop in-app browser or Chrome 149+ with the WebMCP flag.
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
- [ ] All public routes and artifacts work logged out and remain free through judging.

## Devpost and media

- [x] Agent SNR appears consistently as the public product name.
- [ ] Entrant has joined the challenge and personally confirmed age, supported jurisdiction, no exclusion/conflict, entry type, team roster, and authorized Representative where applicable.
- [ ] Entrant personally confirms originality, sole ownership/contributor rights, third-party authorizations, no prohibited Sponsor/Administrator support, no malicious code, and accuracy of every claim.
- [ ] Formal trademark and domain clearance for Agent SNR is complete.
- [ ] The known `AgentOps` technical-identifier and TrailForge Lab screening items are cleared or removed from the final public repo/site/video.
- [x] Description covers WebMCP fit, UX improvement, human-agent collaboration, and implementation.
- [x] Local reference screenshots contain only fictional demo data and original project assets.
- [ ] Public source/license, live URL, test instructions, and any credentials are complete.
- [ ] Public YouTube video has audio, shows actual WebMCP usage, and is under three minutes.
- [ ] Required thumbnail accurately represents the frozen release and contains no secret, private data, or unapproved asset.
- [ ] Recommended gallery evidence: if gallery images are included, use frozen hosted-release captures that contain no secret, private data, or unapproved asset.
- [ ] Repository/About license is detectable; timestamped history is unsquashed.
- [ ] Every link is verified from a logged-out external device.
- [ ] Required Devpost name, tagline, thumbnail, story, Built With tags, Try It Out URL, YouTube link, public-repository URL, team, country/category/custom answers, and terms checkbox are complete in English.
- [ ] Recommended gallery images, if used, are uploaded, legible, rights-cleared, and consistent with the frozen release.
- [ ] Submission is accepted before September 3, 2026 at 1:00 p.m. PDT; receipt is saved and all submitted resources are frozen immediately.
- [ ] Live project remains free and unrestricted through September 21, 2026 at 5:00 p.m. PT; entrant-controlled repo/deployed code and configuration/seed content/video remain unchanged until winner announcement, while normal isolated judge-session data and bounded cleanup continue as designed; later work stays in a fork.
