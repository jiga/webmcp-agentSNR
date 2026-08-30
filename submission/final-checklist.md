# Final submission checklist

Checked items are complete in the prepared repository. Unchecked items require the entrant's accounts, public infrastructure, formal trademark/domain clearance, media, or a dependency release that was not yet available.

## Code and artifacts

- [x] All automated JavaScript, PHP, security, browser, WooCommerce, HPOS-on/off, and artifact tests pass from a clean checkout.
- [ ] WordPress 6.9.4, 7.0.4, and 7.1 pass; WooCommerce 10.9.4, 11.0.1, and final 11.1 pass.
- [x] Official Plugin Check reports zero errors against the extracted ZIP; reviewed warnings are recorded in `verification-report.md`.
- [x] ZIP contains one plugin root, local runtime assets, license/notices, and no development/private files.
- [x] Playground bundle validates, installs, activates, seeds, and reports its iframe limitation accurately.
- [x] Docker one-command bootstrap succeeds with a new named volume.
- [ ] Release tag, ZIP checksum, hosted plugin version, and repository commit match.

## Judged workflow

- [ ] Top-level HTTPS page registers current imperative tools in real ChatGPT and Chrome.
- [x] Site remains human-usable with WebMCP absent.
- [ ] Shopper prompt visibly completes search, compare, policy, and cart.
- [ ] Capability-gap request records non-fulfillment honestly.
- [ ] Checkout handoff creates no order; human demo checkout creates and pays a real Woo order.
- [ ] Order, product evidence, workflow, attribution class, gross/refund/net, and currency correlate correctly.
- [ ] Merchant prompt returns scoped overview, funnel, explanation, health, and gaps.
- [ ] Session policy disables comparison server-side and refreshes browser tools without cross-session effects.
- [ ] Reset creates a clean scope without deleting another judge’s records.

## Security and operations

- [x] Cross-origin/CSRF/replay/schema/size/rate/capability/session-isolation tests pass.
- [x] Redaction/secret checks are clean; cacheable page HTML contains no session or credential values.
- [x] Demo gateway is absent outside explicit demo mode and outbound demo email is disabled in the demo configuration.
- [ ] Monitoring and backups are active; a restore has been rehearsed.
- [ ] All public routes and artifacts work logged out and remain free through judging.

## Devpost and media

- [x] Agent SNR appears consistently as the public product name.
- [ ] Formal trademark and domain clearance for Agent SNR is complete.
- [x] Description covers WebMCP fit, UX improvement, human-agent collaboration, and implementation.
- [ ] Public source/license, live URL, test instructions, and any credentials are complete.
- [ ] Public YouTube video has audio, shows actual WebMCP usage, and is under three minutes.
- [ ] Original screenshots contain no secrets/private data/unapproved assets.
- [ ] Repository/About license is detectable; timestamped history is unsquashed.
- [ ] Every link is verified from a logged-out external device.
- [ ] Submission is accepted before the deadline and all submitted resources are frozen.
