# Devpost rule-compliance checklist

Reviewed September 2, 2026 and rechecked September 3 against the [Official Rules](https://webmcp.devpost.com/rules), [challenge Overview](https://webmcp.devpost.com/), [Resources and FAQ](https://webmcp.devpost.com/resources), and Devpost's current [submission-step guide](https://help.devpost.com/article/126-know-your-submission-steps). The Official Rules control if another page conflicts and may be amended, so the entrant must recheck them again immediately before submitting.

**Displayed submission deadline: September 4, 2026 at 1:00 a.m. PDT.** The live challenge overview shows this extension; the earlier Official Rules copy still shows September 3 at 1:00 p.m. PDT. Retain a screenshot of the live extension and submission receipt.

Repository-local preparation is complete or explicitly tracked below. The entry is **not yet submit-ready** until every unchecked hard blocker and entrant declaration is completed. Automation must never mark a personal/legal declaration complete on the entrant's behalf.

## Stop-the-line submission blockers

- [ ] Entrant has joined the challenge and can reach the final Devpost Submit step.
- [ ] Entrant eligibility, entry type, team/representative authority, ownership, conflicts, and third-party rights are personally confirmed.
- [x] Public repository [`jiga/webmcp-agentSNR`](https://github.com/jiga/webmcp-agentSNR) is live with unsquashed history and a detected root GPL-2.0 license.
- [x] Frozen top-level HTTPS demo at `https://agent-snr.onrender.com/` is publicly reachable and verified in the ChatGPT in-app browser with WebMCP enabled.
- [ ] Public YouTube demo is audible, shows the working project and WebMCP use, contains only rights-cleared material, and is strictly under 3:00 after processing.
- [ ] All Devpost required fields and challenge-specific questions are complete in English.
- [ ] Submission is accepted before the deadline and a receipt/screenshot is retained.
- [ ] Submitted public repo, live site, video, Devpost entry, and any release artifact linked from the entry are frozen immediately after submission and no later than the deadline.

## 1. Dates, access, and freeze

- [x] Repository history begins inside the submission period: August 29, 2026, after the August 25, 2026 11:00 a.m. PDT opening.
- [ ] Re-open the live Official Rules on submission day and record the review time; resolve any amendment before submitting.
- [ ] Submit before the live Devpost deadline of **September 4, 2026 at 1:00 a.m. PDT**; save evidence of the displayed extension and do not rely on proof of sending as proof of receipt.
- [ ] Keep the working project free and unrestricted for judging through **September 21, 2026 at 5:00 p.m. PT**.
- [ ] Keep entrant-controlled submitted repo/deployed code and configuration/seed content/video unchanged until winners are announced on or around **September 23, 2026 at 2:00 p.m. PT**; normal isolated judge-session data and bounded cleanup may continue, and later development uses a separate fork.
- [ ] Permit a post-deadline correction only after explicit Sponsor/Devpost approval and only for the narrow IP, PII, or inappropriate-material reasons allowed by the Rules.

Evidence: Official Rules Sections 1, 6, and 12; Resources FAQ “Can I edit my submission after the deadline?”

## 2. Entrant declarations — human sign-off required

- [ ] Every entrant is at least the age of majority where they reside.
- [ ] Every entrant resides in a jurisdiction that currently supports OpenAI API access and is not excluded or legally prohibited.
- [ ] Entrant is not a Promotion Entity, covered employee/agent/family/household member, judge or judge employer, affiliate, event operator, or other real/apparent conflict.
- [ ] Entry type is selected: individual, team, or organization.
- [ ] If team/organization: final roster is accurate and one eligible person is expressly authorized as Representative.
- [ ] If organization: it existed before entry and is organized/incorporated in a supported jurisdiction.
- [ ] Agent SNR was personally selected and approved by the entrant; formal name/domain/trademark clearance is complete.
- [ ] Submission is the entrant/team/organization's original and solely owned work product; contributor, employer, school, client, and contractor rights are resolved.
- [ ] Every third-party SDK, API, dataset, dependency, asset, font, mark, image, clip, and audio element is authorized and license-compliant.
- [ ] Project received no prohibited financial or preferential support, funding, investment, development contract, or commercial license from Sponsor or Administrator.
- [ ] If another entry is submitted, it is unique and substantially different.
- [ ] Every public claim, screenshot, and video statement has been personally checked for accuracy and does not overstate what is running.

Evidence: Official Rules Sections 3, 4, 7, and 8; [supported countries and territories](https://platform.openai.com/docs/supported-countries).

## 3. Project requirements

- [x] Project is a non-trivial WebMCP-powered web application where people and browser agents collaborate on one website.
- [x] Intended platform is documented: WordPress 6.9+, PHP 8.1+, optional WooCommerce 10.9+, and top-level WebMCP-capable desktop browser.
- [x] Clean install, deterministic builds, exact-ZIP matrix, native smoke, model evals, browser tests, Playground execution, hosted persistence, and official in-app browser checks are documented and passing. Protected model evidence is bound to commit `410c198963ec649ed58e21fce7c80103db3d0ad8`; deployed evidence is bound to commit `e46c9c539ea649a7701e59bad9784cb7012be5b9`.
- [x] `HACKATHON.md` distinguishes pre-period research/specification from in-period executable code and retains timestamped history.
- [x] `THIRD_PARTY_NOTICES.md`, lockfiles, and the GPL-2.0-or-later license document integrations and redistribution terms.
- [ ] Entrant confirms no pre-period source code, artwork, media, or copy was incorporated contrary to the provenance disclosure.

Evidence: Official Rules “Project Requirements,” “New & Existing,” and “Third Party Integrations.”

## 4. Working project and judge access

- [x] Deploy public commit `e46c9c539ea649a7701e59bad9784cb7012be5b9` to stable, top-level HTTPS WordPress hosting on Render.
- [x] Record and verify the public judge-start, storefront, Agent SNR, readiness, and repository URLs; no optional release or Playground URL is claimed.
- [x] Test every submitted public URL without authentication; root, storefront, Agent SNR, readiness, and REST health return HTTP 200.
- [x] Verify both WebMCP surfaces through the ChatGPT in-app browser as an official judge path.
- [x] Confirm 12 storefront and 8 Agent SNR tools are discovered on the intended top-level pages, with successful safe calls on both surfaces.
- [x] Confirm no payment, subscription, geoblock, invitation, local build, iframe-only path, or authentication restriction blocks judging.
- [x] Public judging requires no credentials; no private credential is placed in repository or public submission copy.
- [ ] Assign an availability owner through the judging period.
- [ ] Recommended reliability safeguards: enable monitoring/backups and rehearse restore.

Evidence: Official Rules “How To Enter,” “Submission Requirements,” and “Testing”; Resources FAQ “Do judges test my project?”

## 5. Public repository

- [x] Root `LICENSE` is GPL-2.0-or-later and linked near the top of `README.md`.
- [x] Source code, original assets, lockfiles, Docker reproduction, install instructions, testing instructions, and build scripts are tracked.
- [x] README contains a recognizable `document.modelContext.registerTool({ name, description, inputSchema, execute })` example and links to the real dynamic implementation.
- [x] Secrets, `.env`, raw eval reports, browser profiles, dependencies, release-test state, and generated release binaries are excluded from Git.
- [x] Commit history is linear, timestamped, and wholly inside the submission period.
- [x] Entrant explicitly confirmed the Git author `jiga <i.digg.tech@gmail.com>` and preservation of the existing history for public release.
- [x] Remove the unresolved WordPress.org contributor placeholder from the final plugin metadata; no contributor ID is fabricated.
- [x] Public GitHub remote is `https://github.com/jiga/webmcp-agentSNR`; the complete linear history is pushed to default branch `main` without squashing or rewriting provenance.
- [x] GitHub detects the root license as GPL-2.0 and exposes the repository, API README, and raw README anonymously.
- [x] Repository description and six scoped topics are configured without implying third-party affiliation; no optional release/tag is claimed.
- [x] GitHub secret scanning and push protection are enabled; a local full-history high-confidence credential scan passes, and private `.env`/`.evals` paths were never tracked.

Evidence: Official Rules “Public code repository” and “Intellectual Property”; Resources FAQ confirms there is no private-repository alternative.

## 6. Required text description

- [x] Explicit section: why this use case is a strong fit for WebMCP.
- [x] Explicit section: how it creates a better user experience.
- [x] Explicit section: what people and agents can do together that was difficult or impossible before.
- [x] Explicit section: brief WebMCP implementation explanation.
- [x] Claims distinguish current v0.1 behavior, demo-only gates, verified evidence, limitations, and future direction.
- [x] Judging evidence covers WebMCP Leverage, Execution, Potential Impact, and Creativity & Ambition.
- [ ] Replace every required public-link token in `devpost-description.md` with frozen, logged-out-verified URLs; replace or remove the optional release/Playground rows depending on what is actually submitted.
- [ ] Paste the final copy into Devpost and verify Markdown/formatting using Preview/View.

Evidence: Official Rules “Submission Requirements” and the equally weighted “Judging Criteria.”

## 7. Devpost form packet

- [x] The optional Devpost Hackathons Plugin is not treated as a submission requirement or source of truth; the live form and Official Rules control.
- [x] Project name: **Agent SNR**—pending the entrant's final name-rights confirmation.
- [x] Tagline, 118 characters: **See what website agents did, hear what they experienced, and connect WordPress journeys to verified business outcomes.**
- [x] Suggested Built With tags: `WebMCP`, `WordPress`, `WooCommerce`, `PHP`, `JavaScript`, `REST API`, `Docker`, `Playwright`, `Chrome`.
- [x] Story source: `submission/devpost-description.md`.
- [x] Testing-instructions source: the marked Devpost paste section in `submission/testing-instructions.md`.
- [x] Gallery candidates: ten 1440×900 PNGs listed in `submission/demo-screenshots.md`.
- [x] Thumbnail candidate: `submission/agent-snr-devpost-thumbnail.png` (original 1536×1024, exact 3:2 PNG, under 5 MB, no third-party logos or source screenshot).
- [ ] Team members/invitations and entrant type are final.
- [ ] Required project thumbnail is uploaded and legible in Preview.
- [ ] Recommended gallery images are uploaded and legible in Preview.
- [ ] Try It Out link is the final top-level HTTPS judge-start URL.
- [ ] Public YouTube link is entered and embeds successfully.
- [ ] Public repository URL is entered in the challenge-specific field.
- [ ] Country/residence, category, new-vs-existing, and every other required challenge-specific field are answered truthfully.
- [ ] Terms and Official Rules checkbox is accepted by the entrant/authorized Representative.

Devpost's standard form guidance lists name, tagline, thumbnail, story, Built With tags, Try It Out links, gallery, video, team, and additional challenge questions. The live form remains the final authority for which fields are required.

## 8. Public YouTube video

- [x] The rendered hosted demo is 2:29.64 and includes the functioning storefront, Agent SNR monitor, human checkout boundary, verified outcome, and narration explaining WebMCP.
- [x] Script uses English narration and requires real calls/results rather than mockups.
- [x] Recorder uses the exact hosted storefront and Agent SNR pages, registered tools, no-charge human checkout, and session control; no mock page or fabricated result is substituted.
- [x] Local MP4 runtime is 149.64 seconds, leaving more than thirty seconds of margin; verify the processed YouTube runtime remains below 3:00.
- [ ] Confirm narration is clear and audible throughout.
- [x] Video contains narration only and no music.
- [x] Page-only recording excludes browser chrome, unrelated tabs, notifications, profiles, admin pages, and billing UI.
- [x] Video uses only the hosted project UI, fictional demo catalog, and generated chapter overlays.
- [x] Contact-sheet and final-frame review found no customer data, credential, cookie, token, unrelated content, or unapproved third-party media.
- [ ] Publish on YouTube as Public—not Private, Unlisted, scheduled, age-restricted, or region-restricted—and verify playback logged out.
- [ ] Verify the YouTube embed on the Devpost project page before submission.

Evidence: Official Rules “demonstration video” requirements. The challenge specifically requires public YouTube even though generic Devpost supports other hosts.

## 9. English, security, and rights

- [x] Repository, description, testing instructions, screenshots, deck, and planned narration are in English.
- [x] Checked-in demo data is fictional; checkout uses `.invalid` customer data and a no-charge gateway.
- [x] The Agent SNR workflow ledger stores no raw prompts, addresses, payment fields, or free-form feedback; ordinary WooCommerce remains the system of record for human-submitted order data, and that boundary is documented.
- [x] Root notices state that product artwork/copy is original and no third-party logo, font, music, or executable runtime library is bundled.
- [x] Replace the former public demo-store candidate name with generic **Agent SNR Demo Store** / **Agent SNR / Demo Store** wording before public capture.
- [ ] Complete formal Agent SNR trademark/domain clearance before public launch/video; confirm the canonical `webmcp-agentSNR` repository slug and `wmcp-agentsnr` plugin slug are acceptable.
- [ ] Confirm necessary descriptive WordPress, WooCommerce, WebMCP, ChatGPT, and Chrome references comply with each owner's brand/trademark terms and do not imply endorsement.
- [ ] Confirm the final repository and release contain no malicious code, secrets, PII, or material that violates another party's rights.

Evidence: Official Rules “Language Requirements,” “Submission ownership,” “Intellectual Property,” and Section 8.

## 10. Judging-readiness check

- [x] **WebMCP Leverage:** two real top-level tool surfaces, typed contracts, multi-step workflows, reversible actions, dynamic policy, visible shared state, safety boundaries, and strict evals.
- [x] **Execution:** reproducible plugin, Docker, Playground, exact-ZIP matrix, security tests, model-backed journey, and coherent shopper/operator experience.
- [x] **Potential Impact:** a specific WordPress/WooCommerce operator problem with a reusable open-source path for other WordPress workflows.
- [x] **Creativity & Ambition:** the website observes its own agent interface, captures missed demand and structured testimony, proves business outcomes, and governs the next run.
- [ ] A reviewer unfamiliar with the project can understand all four criteria from the Devpost story, images, and video without opening the live app.

Evidence: Official Rules Stage One and Stage Two. Tie-breaking begins with WebMCP Leverage.

## 11. Pre-submission release record

Complete this table after the final owner decisions—and after the artifact build if publishing one—then commit it before final Submit. The tag/checksum fields are recommended project provenance, not Devpost requirements, and must never delay submission past the live 1:00 a.m. PDT deadline. The table deliberately excludes the commit SHA, because a commit cannot contain its own identifier, and excludes facts that exist only after submission.

| Field | Final value |
|---|---|
| Entrant type / authorized Representative | **TO BE CONFIRMED BY ENTRANT** |
| Rules rechecked at, with timezone | **PENDING** |
| Public repository URL | `https://github.com/jiga/webmcp-agentSNR` |
| Optional annotated release tag | `v0.1.0` if used |
| Optional plugin ZIP SHA-256 | **PENDING IF PUBLISHED** |
| Optional Playground ZIP SHA-256 | **PENDING IF PUBLISHED** |
| Judge-start URL | `https://agent-snr.onrender.com/` |
| Storefront URL | `https://agent-snr.onrender.com/storefront-demo/` |
| Agent SNR URL | `https://agent-snr.onrender.com/agentsnr-demo/` |
| Readiness URL | `https://agent-snr.onrender.com/webmcp-health/` |
| Public YouTube URL and processed duration | **PENDING** |
| Availability/freeze owner | **PENDING** |

Immediately after the final commit and before Submit, save the full commit SHA in a private external release record. If using the optional tag/release workflow, also save `git rev-parse 'v0.1.0^{commit}'`, its result, and the artifact-hash match. After Devpost accepts the entry, append the submission URL, receipt/screenshot, accepted timestamp, and freeze timestamp there. Do not edit the default branch or any submitted tag to add those post-commit or post-submit facts during the freeze.

Final sign-off:

- [ ] Every item marked as a hard blocker is complete.
- [ ] Every personal/legal declaration was checked by the entrant—not by automation.
- [ ] Exact public commit, deployed build, screenshots, video, description, testing instructions, and any linked release artifact describe the same frozen behavior.
- [ ] A logged-out external-device rehearsal passed immediately before submission.
- [ ] Final Devpost View/Preview contains no placeholder, broken link, formatting error, secret, or overclaim.
- [ ] Submission receipt is saved privately and the freeze is active; this post-submit check does not require a frozen-repository edit.
