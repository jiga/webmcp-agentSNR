# Owner actions required before submission

These actions need human identity, accounts, infrastructure, or judgment and are intentionally not fabricated by the repository.

## Identity, release, and hosting

- [ ] Complete formal trademark and domain clearance for **Agent SNR**, including the intended repository slug and public-facing domains; avoid the established AgentOps.ai product association.
- [ ] Confirm the current Git author identity is appropriate for public history.
- [ ] Create the public GitHub/GitLab/Bitbucket repository, set About/license metadata, add the remote, and push without squashing the submission-period commits.
- [ ] Choose entrant type/team roster and confirm challenge eligibility and prize ownership.
- [ ] Provision stable WordPress hosting and an owned domain; configure DNS, HTTPS, origin-wide headers, backups, monitoring, safe email sink, and production secrets.
- [ ] Complete the final WordPress/WooCommerce/PHP dependency matrix, including WooCommerce 11.1 final when available.
- [ ] Deploy the exact release tag and checksummed ZIP to stable, top-level HTTPS storefront and Agent SNR URLs; seed/freeze the public demo so it remains free through September 21, 2026 at 5:00 p.m. PDT.
- [ ] Record the public commit, tag, ZIP SHA-256, hosted plugin version, both final URLs, freeze timestamp, and operations owner.
- [ ] Run the full logged-out public smoke, cache-isolation, two-session, backup, and restore checks from an external device.

## WebMCP external validation

- [x] The remediated protected model-backed selection/browser evals pass the strict all-pass checker at commit `e4d9c86b2754c735094b1dc8437fbd007d3e557a`; the initial failed run remains a superseded baseline in [`webmcp-eval-report-2026-09-01.md`](webmcp-eval-report-2026-09-01.md), and raw `.evals/` reports remain private.
- [ ] Run real ChatGPT desktop Site Tools and Chrome WebMCP testing against both frozen top-level pages; record exact client/browser versions, supported model, discovery counts, prompt results, and sanitized evidence.
- [ ] Install and review **nekuda WebMCP Workbench 1.2.1** in a dedicated Chrome profile. If it auto-updates, record the actual version and re-review permissions/release notes.
- [ ] Complete every Tools, manual-call, Audit, Saved calls, repeated Evals, Logs/approvals, and User Mode gate in [`workbench-validation.md`](workbench-validation.md). A score without the call and journey evidence is not complete.
- [ ] Keep provider keys only in the approved local client configuration. Store raw Workbench/model logs privately; remove keys, authorization data, cookies, CSRF/session values, customer data, and unrelated browsing history before sharing evidence.
- [ ] Run the [WebMCP.com public scanner](https://webmcp.com/) against both frozen top-level HTTPS URLs and resolve any `api-absent`, `api-empty`, `blocked`, `load-error`, unexpected count, or Sensitive Action classification.
- [ ] Follow [`webmcp-directory-listing.md`](webmcp-directory-listing.md): submit the listing manually with a monitored public contact email, wait for review/indexing, and confirm the read-only API lookup returns the intended host/path, catalog, schemas, and categories.
- [ ] Add the final public directory URL and timestamp to README, Devpost, and release evidence. Do not claim the site is listed while review is pending.

## Media, publication, and freeze

- [ ] Capture the original screenshots from the frozen site.
- [ ] Narrate/record the actual workflow, publish the audio-enabled video publicly on YouTube, and confirm duration below three minutes.
- [ ] Replace bracketed URLs in README/submission files and verify every link logged out.
- [ ] Configure a private security-reporting contact before public launch.
- [ ] Tag `v0.1.0-hackathon`, publish checksummed ZIP/Playground artifacts, and verify hosted provenance matches the tag.
- [ ] Complete Devpost fields in English, accept the official rules, submit before September 3, 2026 at 1:00 p.m. PDT, and name the freeze/operations owner.
- [ ] Make no changes to submitted repository, live site, video, or Devpost entry during judging; continue only in a fork.
