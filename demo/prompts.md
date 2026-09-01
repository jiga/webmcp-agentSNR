# Agent SNR demonstration prompts

These are the short prompt cards. For the canonical continuous shopper → human checkout → Agent SNR replay → governance → refund rehearsal, follow [`HACKATHON_RUNBOOK.md`](HACKATHON_RUNBOOK.md).

## Shopper workflow

```text
Start with this site's Agent Guide. Find a waterproof backpack under $100
with at least IPX5 protection. If none match, stop and explain what the site
recorded before changing my constraints.
```

## Evidence-based recovery and feedback

```text
Relax only the water rating. Keep the backpack, in-stock, and under-$100
constraints. Compare the two matches, verify the published return policy,
add the compact option, prepare checkout, and stop at the human handoff.
Then follow the Agent Guide's feedback instructions for this journey.
```

## Checkout handoff

```text
Prepare checkout for the current cart and stop at the normal WooCommerce
handoff. Do not navigate, submit customer data, accept terms, or place the order.
```

## Merchant investigation

```text
Monitor my current agent session, replay its tool and commerce timeline,
identify the slowest or failed invocation, connect it to the commerce outcome,
separate site-observed opportunities from agent-reported feedback and
site-verified measurements, and summarize current controls.
```

## Optional capability-gap compatibility

```text
Find the blue TerraRoll 25 Pack and notify me when it is back in stock.
If the store cannot do that, say so honestly, record the unsupported request,
and do not pretend a notification was created.
```

## Session governance

```text
Disable product comparison for this demo session.
```

Restore:

```text
Clear my session override and restore product comparison for this demo session.
```
