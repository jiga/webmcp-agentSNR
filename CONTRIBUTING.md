# Contributing

Thanks for helping make agent-operated WordPress workflows safer and more observable.

## Before changing code

1. Read `HACKATHON.md`, `SECURITY.md`, and the contract constants under `plugin/wmcp-agentops/src/Contract/`.
2. Start from a focused branch; do not rewrite the public hackathon history.
3. Keep one source of truth for tool names, schemas, risk, policy, rate limits, and callbacks in `ToolCatalog`.
4. Do not add destructive or financial tools, remote SaaS dependencies, arbitrary Ability exposure, raw payload telemetry, or payment submission to the v0.1 scope.

## Local workflow

```bash
npm install
./bin/start-demo.sh
npm run verify
```

Add positive, negative, and boundary tests. For changes that affect WordPress/WooCommerce behavior, verify the containerized integration suite and both HPOS modes. For runtime/UI changes, test the unsupported-browser fallback, iframe skip, cancellation, visible state, and a real WebMCP client before release.

## Coding expectations

- PHP 8.1+, strict types, WordPress Coding Standards, escaped output, prepared SQL, and WooCommerce CRUD.
- Vanilla browser JavaScript with no remote runtime code.
- Accessible semantic HTML, keyboard/focus behavior, reduced-motion support, and bounded ARIA-live updates.
- Stable, actionable public errors without stack traces or exception messages.
- UTC timestamps, raw 26-character ULIDs in fixed-width columns, and 64-character hex hashes.
- No user-specific values in cacheable HTML.

## Commit convention

```text
feat(runtime): register storefront tools through current WebMCP API
feat(woo): correlate demo order to workflow
fix(security): bind guest CSRF token to demo session
test(attribution): cover partial refund net revenue
docs(submission): add judge testing path
```

A pull request should state behavior, tests run, security/privacy impact, schema/migration impact, and any release-gate change. UI changes should include a screenshot or short recording made from the actual implementation.
