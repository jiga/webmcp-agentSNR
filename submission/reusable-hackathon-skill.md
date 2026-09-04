# Reusable hackathon submission skill

The Agent SNR submission-readiness workflow has been generalized and installed as a personal Codex skill:

- Skill: `$hackathon-submission-readiness`
- Entrypoint: `/Users/jignesh/.codex/skills/hackathon-submission-readiness/SKILL.md`
- Invocation: “Use `$hackathon-submission-readiness` to audit and prepare this project for its current Devpost hackathon.”

The skill is intentionally personal rather than repository-bound so it can be discovered from future projects on this machine. To move it to another machine, copy the complete `hackathon-submission-readiness` directory into that installation’s Codex skills directory.

## Included guidance

- current-rule and live-form audit;
- Devpost field, owner-declaration, save, submit, and freeze boundaries;
- product story and release-identity lock;
- public repository and exact-release preparation;
- deployment selection, persistence, idempotency, cost, and judge access;
- layered tests, agent evals, security, screenshots, and provenance;
- context-first pitch and live-demo video production;
- logged-out final audit, submission receipt, and judging-period freeze.

Supporting references are split by task so future runs load only what they need:

- `references/rules-and-devpost.md`
- `references/product-repository-deployment.md`
- `references/verification-and-evidence.md`
- `references/demo-video.md`
- `references/final-submission-runbook.md`

The deterministic `scripts/verify_demo_video.sh` helper reports duration, streams, encoding, resolution, frame rate, loudness, long silence, size, and SHA-256. It fails when the hard duration or required-stream checks fail.

## Validation record

- Skill structure: `quick_validate.py` passed.
- Scaffold placeholders: none remain.
- Shell syntax: passed.
- Positive video check: the final 165.52-second Agent SNR MP4 passed.
- Negative duration check: the same file correctly failed against a 100-second limit.
- Official example links were re-opened on September 3, 2026:
  - <https://webmcp.devpost.com/>
  - <https://webmcp.devpost.com/rules>
  - <https://webmcp.devpost.com/resources>
  - <https://help.devpost.com/article/126-know-your-submission-steps>

The example event URLs remain in the skill, but its deadlines, eligibility, required fields, and deliverables are never treated as reusable facts. Every future run must read that event’s current pages and live submission form.
