# Sub-three-minute video script

Final future-world pitch and demo: **2:47.00**, natural English AI narration, public-YouTube ready, no music, and no third-party mark or material without documented permission.

| Time | Picture | Narration / action |
|---:|---|---|
| 0:00–0:05.5 | Real WebMCP cold open | Successful `search_products` call, zero matches, and the decision to relax one constraint. “The search worked. Zero results. That is a business signal.” |
| 0:05.5–0:11 | Future world | “A web where every person has an agent.” One complete narration thought finishes before the next dissolve. |
| 0:11–0:17.5 | Owner blind spot | “The store sees the call. It misses the intent.” Three plain owner questions remain readable. |
| 0:17.5–0:25 | Agent SNR | WordPress operations-layer card uses the exact live storefront frame and match-dissolves into the demo. |
| 0:25–0:41 | Shopper prompt and guide | Full prompt, twelve registered tools, `get_agent_guide`, `get_cart`, and human-owned checkout boundary. |
| 0:41–0:57 | IPX5 missed demand | Exact search arguments, successful call, zero structured matches, and privacy-safe opportunity evidence. |
| 0:57–1:17 | Controlled recovery | IPX4 search, product comparison, return policy, and HarborLite recommendation with the original constraint still visible. |
| 1:17–1:37 | Prepare and stop | Reversible cart, checkout handoff, handoff-linked agent feedback, and an explicit stop before commitment. |
| 1:37–1:53 | Human outcome | The shopper places the fictional no-charge order; the resulting conversion is verified rather than inferred. |
| 1:53–2:11 | Owner agent | L-cut from the receipt into eight owner tools, attributed order, and missed IPX5 demand. |
| 2:11–2:31 | Evidence chain | Exact workflow replay links zero result, recovery, feedback, human checkpoint, and converted order while preserving evidence sources. |
| 2:31–2:47 | Business decision | Stay on the live opportunity signal: add IPX5 coverage and keep the proven IPX4 recovery path until then. |

## Rendered artifact

- Preferred submission video: `dist/agent-snr-devpost-demo-final.mp4`
- Superseded pitch-card cut: `dist/agent-snr-devpost-demo-v3.mp4`
- Superseded demo-only cut: `dist/agent-snr-devpost-demo-v2.mp4`
- Superseded walkthrough cut: `dist/agent-snr-devpost-demo-natural.mp4`
- Original system-voice video: `dist/agent-snr-devpost-demo.mp4`
- Narration transcript: `dist/agent-snr-devpost-narration.txt`
- Recorder: `demo/record-hosted-demo.mjs`
- Natural narration remaster: `demo/remaster-natural-narration.mjs`
- Pitch-card compositor: `demo/add-pitch-intro.mjs`
- Future-world cards: `demo/render-future-story-cards.mjs`
- Story compositor: `demo/build-future-story-visual.mjs`
- Duration: `167.00` seconds
- Encoding: 1920×1080 progressive H.264 High, 25 fps, stereo AAC
- Preferred size: 13,922,187 bytes
- Preferred SHA-256: `309fa6f26cb2b580342b3cd3166d51a50cfb95f749383d3388f934b6eaa030ae`
- Natural voice: OpenAI `gpt-4o-mini-tts`, `marin`, twelve separately directed beats, all rendered at natural 1.0× tempo
- Audio QA: mean −19.3 dB, peak −1.5 dB, no silence over three seconds at −45 dB
- Story QA: the real product appears in the first frame; warm-white cards use 500 ms dissolves and one spoken thought each; the solution card uses the exact live frame for a match dissolve at 25 seconds. The compositor consumes measured scene boundaries and fails if hosted timing drifts.
- Interaction proof: the visible Browser Agent panel receives the prompt, arguments, and result summary from the same registered `definition.execute()` calls that update the hosted page.
- Disclosure: identify the narration as AI-generated in the YouTube description and Devpost media notes.

## Recording checklist

- Record the exact frozen public commit and seeded release configuration; normal isolated judge-session state may change as designed. If using the optional release tag, record that same tagged build.
- Show the functioning hosted project and clearly explain both what was built and how the page uses `document.modelContext.registerTool()`.
- Keep browser zoom high enough to read tool names, evidence, order status, and policy result.
- Show actual calls and visible responses; do not substitute mockups.
- Keep the edit at or below 2:50 and confirm the processed YouTube duration is strictly below 3:00.
- Use clear English narration throughout; review auto-captions and correct them if captions are published.
- Use original artwork and narration only; no copyrighted music.
- Crop browser/app chrome, other tabs, profile details, notifications, and nonessential third-party names or logos from every frame.
- Confirm that the generic Agent SNR demo-store wording is present in the frozen build and regenerated evidence; complete Agent SNR trademark/domain clearance before recording.
- Audit necessary descriptive compatibility references against the applicable brand terms and record the entrant's approval.
- Publish as **Public**, verify playback and audio logged out, and confirm the video embeds on the Devpost project page.
- Open all public links logged out before and immediately after publishing.
- Freeze the uploaded video at final submission and make no changes after the live Devpost deadline of September 4, 2026 at 1:00 a.m. PDT without explicit Sponsor/Devpost permission; retain evidence of the displayed extension because the earlier rules copy shows the original deadline.
