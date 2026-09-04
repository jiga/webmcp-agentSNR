# Sub-three-minute video script

Final context-first pitch and demo: **2:45.52**, natural English AI narration, public-YouTube ready, no music, and no third-party mark or material without documented permission.

| Time | Picture | Narration / action |
|---:|---|---|
| 0:00–0:04.5 | Agent SNR title | Establish the product and its outcome-monitoring purpose before showing any telemetry. |
| 0:04.5–0:10 | Future world | Explain the world where everyone delegates browsing and shopping to an agent. |
| 0:10–0:16.5 | Owner blind spot | Explain that successful calls can still hide shopper intent, recovery, and unmet demand. |
| 0:16.5–0:23.5 | Agent SNR solution | Introduce the WordPress/WebMCP operations layer and match-dissolve into its exact live frame. |
| 0:23.5–0:39.5 | Shopper prompt and guide | Full prompt, twelve registered tools, `get_agent_guide`, `get_cart`, and human-owned checkout boundary. |
| 0:39.5–0:55.5 | IPX5 missed demand | Exact search arguments, successful call, zero structured matches, and privacy-safe opportunity evidence. |
| 0:55.5–1:15.5 | Controlled recovery | IPX4 search, product comparison, return policy, and HarborLite recommendation with the original constraint still visible. |
| 1:15.5–1:35.5 | Prepare and stop | Reversible cart, checkout handoff, handoff-linked agent feedback, and an explicit stop before commitment. |
| 1:35.5–1:51.5 | Human outcome | The shopper places the fictional no-charge order; the resulting conversion is verified rather than inferred. |
| 1:51.5–2:09.5 | Owner agent | L-cut from the receipt into eight owner tools, attributed order, and missed IPX5 demand. |
| 2:09.5–2:29.5 | Evidence chain | Exact workflow replay links zero result, recovery, feedback, human checkpoint, and converted order while preserving evidence sources. |
| 2:29.5–2:45.52 | Business decision | Stay on the live opportunity signal: add IPX5 coverage and keep the proven IPX4 recovery path until then. |

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
- Duration: `165.52` seconds
- Encoding: 1920×1080 progressive H.264 High, 25 fps, stereo AAC
- Preferred size: 13,783,232 bytes
- Preferred SHA-256: `a01396b39444a1b44051e7de83dc4661c470f87f3a3b57309f24f3f81e118ccc`
- Natural voice: OpenAI `gpt-4o-mini-tts`, `marin`, twelve separately directed beats; eleven run at natural 1.0× and the 4.5-second title uses 1.013×
- Audio QA: mean −19.1 dB, peak −1.4 dB, no silence over three seconds at −45 dB
- Story QA: four warm-white presentation cards establish product, world, problem, and solution before the demo. Each uses one spoken thought and 500 ms dissolves; the solution card uses the exact live frame for a match dissolve at 23.5 seconds. The compositor consumes measured scene boundaries and fails if hosted timing drifts.
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
