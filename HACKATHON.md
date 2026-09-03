# Hackathon provenance

The OpenAI WebMCP Challenge submission period opened August 25, 2026 at 11:00 a.m. PDT. This repository's executable implementation began August 29, 2026 at 10:45 p.m. PDT with root commit `acba796`.

## New work created during the submission period

- Secure WordPress plugin bootstrap, lifecycle, database schema, and native Abilities catalog.
- Top-level imperative WebMCP runtime, dynamic manifests, same-origin REST execution, policy, rate limits, replay protection, and visible shared state.
- WooCommerce search, comparison, cart, human checkout handoff, order/refund correlation, and deterministic attribution.
- Agent SNR monitoring, Workflow Replay, Signals, tool health, session controls, Agent Guide, structured feedback, and automatic opportunity detection.
- Fictional demo catalog, original SVG product artwork, WordPress templates, Docker/Playground reproduction, tests, evals, screenshots, deck, and submission documentation.

## Pre-period material

Pre-period work consisted only of market research, product architecture, and written specification. The project record states that no pre-existing executable application code was incorporated. The source, demo artwork, screenshots, and packaged submission assets visible in this repository were first committed during the challenge period.

## Timestamped evidence

| Date (PDT) | Commit | Evidence milestone |
|---|---|---|
| August 29 | `acba796` | Repository scaffold, license, disclosure, and release/test skeleton |
| August 30 | `1e8433e`–`6d6c694` | Plugin implementation, reproducible acceptance, monitoring UX, demo, and WooCommerce hardening |
| August 31 | `e27c680`–`bad782f` | Agent Guide, feedback, opportunity signals, and clarified demo behavior |
| September 1 | `e853062`–`c58a294` | WebMCP contract alignment, protected eval remediation, passing evidence, and exact-artifact verification |

Verify the complete chronological record locally with:

```bash
git log --reverse --date=iso-strict --format='%h %aI %an %s'
```

The history is intentionally linear and unsquashed. Do not rebase, squash, amend, or rewrite it until winners are announced on or around September 23, 2026 at 2:00 p.m. PT.

## Assistance and entrant responsibility

AI-assisted engineering was used for implementation, debugging, testing, and documentation. The challenge FAQ permits this form of assistance. The entrant remains responsible for the product idea, personally selected public name, final technical and factual review, ownership, license compliance, eligibility, and every representation made in the submission.

Before submission, the entrant must personally complete the identity, contributor-rights, employer/client/school ownership, third-party authorization, name/trademark, conflict, and Sponsor/Administrator-support declarations in [`submission/devpost-rules-checklist.md`](submission/devpost-rules-checklist.md). Automation does not make those legal declarations.
