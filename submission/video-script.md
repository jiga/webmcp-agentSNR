# Sub-three-minute video script

Rendered pitch-and-demo take: **2:49.40**, natural English AI narration, public-YouTube ready, no music, and no third-party mark or material without documented permission.

| Time | Picture | Narration / action |
|---:|---|---|
| 0:00–0:03.5 | Title card | Agent SNR and its agent outcome monitoring promise. |
| 0:03.5–0:07 | Problem card | A successful tool call can still hide missed demand and the agent's recovery. |
| 0:07–0:10.5 | Architecture card | Browser agent, WebMCP runtime, plugin execution, human checkpoint, redacted signals ledger, and verified commerce. |
| 0:10.5–0:17 | Working hosted product | Live Agent SNR page and Browser Agent panel appear before fifteen seconds. |
| 0:17–0:33 | Shopper prompt, discovery, Agent Guide | Visible Browser Agent panel shows the natural-language request, twelve registered tools, `get_agent_guide`, and `get_cart`. |
| 0:33–0:51 | IPX5 zero-result call | `search_products` shows real arguments, zero structured matches, and the agent's decision to relax only water rating. |
| 0:51–1:15 | IPX4 recovery | Second search, `compare_products`, `get_store_policy`, structured results, and the evidence-backed compact recommendation. |
| 1:15–1:37 | Cart, feedback, and handoff | `add_to_cart`, `prepare_checkout_handoff`, `report_agent_feedback`, result receipts, and explicit agent stop. |
| 1:37–1:55 | Human checkout | Human-only review and click produces the fictional no-charge order and verified outcome. |
| 1:55–2:13 | Owner prompt and operator tools | Owner asks what happened; agent calls analytics, funnel, health, workflows, and opportunity signals. |
| 2:13–2:35 | Investigation and replay | Owner agent investigates IPX5 demand, calls `explain_agent_workflow`, and connects recovery to verified conversion. |
| 2:35–2:49.36 | Bounded owner action | `set_tool_enabled` disables comparison for only the demo session and returns server enforcement evidence. |

## Rendered artifact

- Preferred submission video: `dist/agent-snr-devpost-demo-v3.mp4`
- Superseded demo-only cut: `dist/agent-snr-devpost-demo-v2.mp4`
- Superseded walkthrough cut: `dist/agent-snr-devpost-demo-natural.mp4`
- Original system-voice video: `dist/agent-snr-devpost-demo.mp4`
- Narration transcript: `dist/agent-snr-devpost-narration.txt`
- Recorder: `demo/record-hosted-demo.mjs`
- Natural narration remaster: `demo/remaster-natural-narration.mjs`
- Pitch-card compositor: `demo/add-pitch-intro.mjs`
- Duration: `169.40` seconds
- Encoding: 1920×1080 progressive H.264 High, 25 fps, stereo AAC
- Preferred size: 12,899,034 bytes
- Preferred SHA-256: `016a6d68c2ef03c1b03912e285e840d37b2e3bdbba5457cd4b68905f72d45aa3`
- Natural voice: OpenAI `gpt-4o-mini-tts`, `marin`, nine separately directed scenes; timing range 0.82×–0.984× in the final take
- Audio QA: mean −19.1 dB, peak −1.8 dB, no silence over three seconds at −45 dB
- Pitch QA: deck slides 1, 2, and 4 occupy 3.5 seconds each; the working hosted product appears at 10.5 seconds and the complete demo remains intact.
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
