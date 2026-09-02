# Final submission checklist

Checked items are complete in the prepared repository. Unchecked items require the entrant's accounts, public infrastructure, formal trademark/domain clearance, media, or a dependency release that was not yet available.

## Code and artifacts

- [x] All automated JavaScript, PHP, security, browser, WooCommerce, HPOS-on/off, and artifact tests pass from a clean checkout.
- [x] Exact-ZIP WordPress 6.9 / WooCommerce 10.9.4, WordPress 7.0.4 / WooCommerce 11.0.1, and WordPress 7.1 / WooCommerce 11.0.1 HPOS pass.
- [ ] Re-run the matrix against final WooCommerce 11.1 if it releases before tagging.
- [x] Official Plugin Check reports zero errors against the extracted ZIP; reviewed warnings are recorded in `verification-report.md`.
- [x] ZIP contains one plugin root, local runtime assets, license/notices, and no development/private files.
- [x] Playground bundle validates, installs, activates, seeds, and reports its iframe limitation accurately.
- [x] Docker one-command bootstrap succeeds with a new named volume.
- [x] Isolated `agent-snr-showcase` rehearsal runs the verified release ZIP without touching development volumes.
- [x] Editable 12-slide architecture/demo presentation and ten real exact-artifact local-flow reference screenshots are included.
- [ ] Release tag, ZIP checksum, hosted plugin version, and repository commit match.

## WebMCP quality and external evidence

- [x] The pinned GoogleChromeLabs WebMCP Evals keyless smoke passes 5/5 storefront and 6/6 Agent SNR calls on the exact release in disposable localhost Chrome, including application-error-envelope enforcement.
- [ ] Protected model-backed selection and browser evals were executed on September 1, 2026, but the strict all-pass gate failed. Fix the recorded selection, journey-ordering, and feedback-path findings, then rerun to the thresholds in [`webmcp-readiness-design.md`](webmcp-readiness-design.md); see [`webmcp-eval-report-2026-09-01.md`](webmcp-eval-report-2026-09-01.md).
- [ ] Real ChatGPT desktop Site Tools and Chrome discover exactly 12 storefront and 8 Agent SNR canonical tools on the frozen top-level HTTPS pages.
- [ ] The owner completed [`workbench-validation.md`](workbench-validation.md) with Workbench 1.2.1 (or a separately reviewed newer version), exact release identity, manual calls, Audit findings, saved-call replay, repeated evals, approval/log review, and User Mode evidence.
- [ ] Provider keys and raw `.evals/`/Workbench logs remain private; every linked excerpt or screenshot is sanitized.
- [ ] The WebMCP.com public scanner passes both frozen pages with expected catalogs and no API/load/blocking or unexplained Sensitive Action result.
- [ ] The human directory request is approved, post-index API lookups return `supported: true` for the intended public representation, and the final directory URL is recorded using [`webmcp-directory-listing.md`](webmcp-directory-listing.md).

## Judged workflow

- [ ] Both top-level HTTPS pages register their current imperative catalogs in real ChatGPT and Chrome.
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
- [ ] Monitoring and backups are active; a restore has been rehearsed.
- [ ] All public routes and artifacts work logged out and remain free through judging.

## Devpost and media

- [x] Agent SNR appears consistently as the public product name.
- [ ] Formal trademark and domain clearance for Agent SNR is complete.
- [x] Description covers WebMCP fit, UX improvement, human-agent collaboration, and implementation.
- [x] Local reference screenshots contain only fictional demo data and original project assets.
- [ ] Public source/license, live URL, test instructions, and any credentials are complete.
- [ ] Public YouTube video has audio, shows actual WebMCP usage, and is under three minutes.
- [ ] Frozen hosted-release screenshots contain no secrets/private data/unapproved assets and replace or supplement the local reference captures.
- [ ] Repository/About license is detectable; timestamped history is unsquashed.
- [ ] Every link is verified from a logged-out external device.
- [ ] Submission is accepted before the deadline and all submitted resources are frozen.
