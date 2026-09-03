# WebMCP.com scanner and directory handoff

Status: **PENDING FROZEN HTTPS DEPLOYMENT AND HUMAN SUBMISSION**

This is optional supplemental discovery evidence, not a Devpost submission requirement or release blocker.

This runbook distinguishes three different checks:

1. the public **scanner** loads a live page and evaluates its registered tools;
2. the read-only **directory API** reports what WebMCP.com has indexed, not what an unlisted site currently serves;
3. the **Request listing** form is a human submission that needs a public contact email.

Do not treat a pre-listing API response with `"supported": false` as a scanner failure. Localhost, preview URLs, mutable deployments, and private tunnels are not submission targets.

## Freeze record

| Field | Recorded value |
|---|---|
| Public storefront URL | `[PUBLIC_STOREFRONT_URL]` |
| Public Agent SNR URL | `[PUBLIC_AGENT_SNR_URL]` |
| Public repository URL | `[PUBLIC_REPOSITORY_URL]` |
| Commit | `[FULL COMMIT SHA]` |
| Release tag | `[TAG]` |
| Plugin ZIP SHA-256 | `[SHA-256]` |
| Hosted plugin version | `[VERSION]` |
| Public contact email | `[PUBLIC_CONTACT_EMAIL]` |
| Freeze date/time and timezone | `[YYYY-MM-DD HH:MM TZ]` |

Both product surfaces must be normal top-level HTTPS pages. Confirm their final redirects remain on the intended origin and that each page exposes its own outcome-oriented catalog on load.

## 1. Run the public scanner

1. Open [webmcp.com](https://webmcp.com/) in a logged-out browser.
2. In **Scan your website**, paste the exact frozen storefront URL and run the scan.
3. Repeat with the exact frozen Agent SNR URL.
4. Record the timestamp, final URL, discovered count, inferred tool categories, quality findings, and result link or sanitized screenshot for each surface.
5. Resolve every `api-absent`, `api-empty`, `blocked`, or `load-error` result described by the [scorecard methodology](https://webmcp.com/methodology). Deploy a new release candidate and rerun all gates; do not patch the frozen site in place.
6. Review usability findings before optimizing for raw tool count. The methodology weights usability at 60%, coverage at 20%, and quality at 20%.

| Surface | Expected catalog | Expected trust mix | Scan result / evidence |
|---|---|---|---|
| Storefront | 12 canonical tools | Answer + reversible Action; zero Sensitive Action | `[RESULT, SCORE, URL]` |
| Agent SNR | 8 canonical tools | Answer + session-only Action; zero Sensitive Action | `[RESULT, SCORE, URL]` |

The scanner infers Answer, Action, and Sensitive Action from names, descriptions, and schemas. Investigate any Sensitive Action classification: `prepare_checkout_handoff` must not place an order, accept terms, submit customer data, or process payment.

## 2. Check the read-only directory API

The [API documentation](https://webmcp.com/api-docs) identifies `GET /api/v1/lookup?url=…` as the one-shot directory lookup. Set these values locally, then save the JSON response as private release evidence:

```bash
AGENT_SNR_STOREFRONT_URL='https://replace.example/storefront-demo/'
AGENT_SNR_MONITOR_URL='https://replace.example/agentsnr-demo/'

curl --fail-with-body --silent --show-error --get \
  'https://webmcp.com/api/v1/lookup' \
  --data-urlencode "url=$AGENT_SNR_STOREFRONT_URL"

curl --fail-with-body --silent --show-error --get \
  'https://webmcp.com/api/v1/lookup' \
  --data-urlencode "url=$AGENT_SNR_MONITOR_URL"
```

Before listing, `ok: true` with `supported: false` is expected. After approval/indexing, both lookups must return `ok: true`, `supported: true`, the intended matched host or path, the frozen canonical URL, and the reviewed tools/schemas. If WebMCP.com represents both surfaces as one host-level record, confirm with the directory owner how the second page should be exposed instead of claiming both catalogs were indexed.

Record the final result:

| URL | `supported` | Matched host/path | Tool count | Schema/category review | Evidence |
|---|---:|---|---:|---|---|
| `[PUBLIC_STOREFRONT_URL]` | `[true/false]` | `[VALUE]` | `[COUNT]` | `[PASS/FAIL]` | `[PRIVATE JSON / PUBLIC LINK]` |
| `[PUBLIC_AGENT_SNR_URL]` | `[true/false]` | `[VALUE]` | `[COUNT]` | `[PASS/FAIL]` | `[PRIVATE JSON / PUBLIC LINK]` |

## 3. Request the human listing

1. On [webmcp.com](https://webmcp.com/), choose **Register site** / **Request listing**.
2. Enter the frozen public URL and `[PUBLIC_CONTACT_EMAIL]`. The owner—not an agent or CI job—submits the request.
3. Use the copy below if a description or reviewer note is requested.
4. If the directory accepts one host-level entry, request one primary listing and identify both page paths in the reviewer note. Request separate path-scoped entries only if the directory supports or asks for them.
5. Respond to ownership/quality questions through the public contact, then rerun the API lookups after indexing.
6. Add the final public directory URL to README, Devpost, and the release evidence. Do not claim “listed” while review is pending.

## Ready-to-paste listing copy

### Name

Agent SNR — agent outcome monitoring for WordPress

### Short description

Co-browsing WooCommerce demo where agents discover products and prepare a reversible cart, humans own checkout, and operators replay privacy-safe outcomes.

### Reviewer note

Agent SNR is an open-source hackathon demo with two top-level WebMCP surfaces on one WordPress site. The storefront exposes 12 canonical tools for guide discovery, product search and comparison, published policy facts, reversible cart changes, checkout preparation, and optional structured feedback. The Agent SNR page exposes 8 canonical tools for session-scoped workflow evidence, funnel and health analysis, opportunity signals, diagnostics, and reversible session policy. No WebMCP tool can place, cancel, or refund an order, accept terms, submit customer data, or process payment. The ordinary WooCommerce checkout UI is the human confirmation boundary. Both pages remain useful when WebMCP is unavailable.

Primary storefront: `[PUBLIC_STOREFRONT_URL]`

Agent SNR monitor: `[PUBLIC_AGENT_SNR_URL]`

Source: `[PUBLIC_REPOSITORY_URL]`
Release/tag: `[TAG]` / `[FULL COMMIT SHA]`

### Classification requested

- Site type: **Demo**
- API surface: **Spec / imperative `document.modelContext.registerTool()`**
- Storefront: **Answer + Action; no Sensitive Action**
- Agent SNR: **Answer + Action; no Sensitive Action**

The directory assigns final classifications from the scanned contract. This text describes intended behavior; it does not override the scanner.

## Publication sign-off

- [ ] Both frozen pages pass the public scanner without API/load/blocking failures.
- [ ] Counts, schemas, names, descriptions, and inferred trust categories match the reviewed release.
- [ ] The owner submitted the listing with a monitored public contact email.
- [ ] Directory review is complete; the request is not merely pending.
- [ ] Post-index API lookups return `supported: true` for the intended public representation.
- [ ] Public directory, repository, demo, video, and Devpost links work logged out.
- [ ] The directory URL and timestamp are recorded below.

Directory URL: `[PUBLIC_DIRECTORY_URL]`

Indexed at: `[YYYY-MM-DD HH:MM TZ]`

Owner: `[NAME]`

Decision: `[PASS / BLOCKED]`
