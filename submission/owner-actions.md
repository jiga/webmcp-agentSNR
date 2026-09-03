# Owner actions required before submission

These actions need human identity, accounts, infrastructure, or judgment and are intentionally not fabricated by the repository.

Complete the hard blockers and entrant declarations in [`devpost-rules-checklist.md`](devpost-rules-checklist.md) before the **September 3, 2026 at 1:00 p.m. PDT** deadline. Recheck the live Official Rules immediately before submitting.

## Identity, release, and hosting

- [ ] Complete formal trademark and domain clearance for **Agent SNR**, including the intended repository slug and public-facing domains. The retired third-party-overlapping technical identifiers have been removed from the current tree.
- [x] Replace the former public demo-store candidate name with the generic **Agent SNR Demo Store** site title and **Agent SNR / Demo Store** storefront lockup.
- [x] The owner explicitly chose to preserve the unsquashed history, including the development codename in 19 earlier commit trees and one earlier commit subject, for hackathon provenance.
- [x] The owner explicitly approved public history with Git author `jiga <i.digg.tech@gmail.com>`.
- [x] Remove the unresolved WordPress.org contributor placeholder from the plugin metadata; do not invent a contributor ID.
- [ ] Record the entrant/team/organization's public copyright holder and contributor-rights basis.
- [x] Public repository [`jiga/webmcp-agentSNR`](https://github.com/jiga/webmcp-agentSNR) is live with default branch `main`, description/topics, detected GPL-2.0 license, and unsquashed submission-period commits.
- [ ] Choose entrant type/team roster; confirm age, supported jurisdiction, exclusions/conflicts, prize ownership, and the eligible authorized Representative where applicable.
- [ ] Confirm originality/sole ownership, employer/school/client/contractor rights, every third-party authorization, no prohibited Sponsor/Administrator support, and no other substantially similar entry.
- [x] Render hosts the public WordPress demo at `https://agent-snr.onrender.com/` with generated production secrets and unrestricted logged-out access.
- [ ] Recommended reliability safeguards: add an owned domain/DNS if useful, monitoring, backups/restore, origin-wide headers, and a safe email sink.
- [ ] Optional newest-release evidence: if time permits, recheck WooCommerce 11.1 and rerun the matrix if a stable release exists before the final tag; the declared 10.9.4/11.0.1 range already passes.
- [x] Deploy commit `e46c9c539ea649a7701e59bad9784cb7012be5b9` to the stable top-level HTTPS storefront and Agent SNR URLs; two deploys preserve the seeded catalog and health state.
- [x] Record commit `e46c9c5`, Agent SNR 0.1.0, public URLs, and the September 3, 2026 deployment verification in the repository; no optional release tag is claimed.
- [ ] Assign the named availability/operations owner through the judging window.
- [ ] Run the logged-out public smoke, cache-isolation, and two-session checks from an external device.
- [ ] Recommended: enable monitoring/backups and rehearse restore before the judging window.

## WebMCP external validation

- [x] The protected model-backed selection/browser evals pass all three strict provenance checks against rename commit `410c198963ec649ed58e21fce7c80103db3d0ad8`; the initial and first rename-bound failures remain superseded baselines in [`webmcp-eval-report-2026-09-01.md`](webmcp-eval-report-2026-09-01.md), and raw `.evals/` reports remain private.
- [x] Run the ChatGPT desktop in-app browser against both frozen top-level pages: discover 12 storefront and 8 Agent SNR tools, invoke safe tools, and verify shared session workflow evidence.
- [ ] Optional: install and review **nekuda WebMCP Workbench** in a dedicated Chrome profile; record the actual version and re-review permissions/release notes.
- [ ] Optional: complete the Tools, manual-call, Audit, Saved calls, repeated Evals, Logs/approvals, and User Mode evidence in [`workbench-validation.md`](workbench-validation.md).
- [ ] Keep provider keys only in the approved local client configuration. Store raw Workbench/model logs privately; remove keys, authorization data, cookies, CSRF/session values, customer data, and unrelated browsing history before sharing evidence.
- [ ] Optional: run the [WebMCP.com public scanner](https://webmcp.com/) against both frozen top-level HTTPS URLs and resolve any `api-absent`, `api-empty`, `blocked`, `load-error`, unexpected count, or Sensitive Action classification.
- [ ] Optional: follow [`webmcp-directory-listing.md`](webmcp-directory-listing.md), wait for review/indexing, and add a directory URL only after the read-only API confirms the intended public representation.

## Media, publication, and freeze

- [ ] Verify the required thumbnail accurately represents the frozen release; if including the optional gallery, capture its original screenshots from that release.
- [ ] Narrate/record the actual workflow, publish the audio-enabled video as Public on YouTube, and confirm its processed duration is strictly below three minutes.
- [ ] Audit every final frame and audio element for original/authorized media, third-party marks, PII, credentials, notifications, and unrelated browser content.
- [ ] Replace required URL tokens in README, the Devpost story, the marked Devpost testing-instructions section, and every public/video field; replace or remove optional release/Playground rows, then verify each submitted link logged out. Clearly optional Workbench/directory evidence sheets may remain unfilled templates when not pursued.
- [ ] Recommended launch hygiene: configure a private security-reporting contact.
- [ ] Recommended project provenance—not a Devpost requirement: tag the approved commit as `v0.1.0`, publish checksummed ZIP/Playground artifacts, wait for the release workflow to pass, and verify hosted provenance matches the immutable tag. Do not miss the Devpost deadline for optional release automation.
- [ ] Complete every Devpost standard and challenge-specific field in English, preview the page, accept the Official Rules, submit before September 3, 2026 at 1:00 p.m. PDT, save the receipt, and name the freeze/operations owner.
- [ ] Freeze entrant-controlled repository/default branch/tag, release assets, deployed code/configuration, seed catalog/policies, video, and Devpost entry immediately after final submission and no later than the deadline. Normal isolated judge-session workflows, carts, orders, resets, and bounded cleanup may continue as designed. Continue development only in a separate fork; make a permitted IP/PII correction only after explicit Sponsor/Devpost approval.
- [ ] Keep the project free and unrestricted through September 21, 2026 at 5:00 p.m. PT and keep entrant-controlled submitted materials unchanged until winners are announced on or around September 23 at 2:00 p.m. PT.
