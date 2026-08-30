# Agent SNR demonstration prompts

These are the short prompt cards. For the canonical continuous shopper → human checkout → Agent SNR replay → governance → refund rehearsal, follow [`HACKATHON_RUNBOOK.md`](HACKATHON_RUNBOOK.md).

## Shopper workflow

```text
Find a waterproof backpack under $120, compare the two best choices,
confirm that the return policy is at least 30 days, and add the best-value
option to my cart. Do not start checkout.
```

## Unsupported request

```text
Find the blue TerraRoll 25 Pack and notify me when it is back in stock.
If the store cannot do that, say so honestly, record the capability gap,
and do not pretend a notification was created.
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
show any capability signals this store does not support, and summarize current controls.
```

## Session governance

```text
Disable product comparison for this demo session.
```

Restore:

```text
Clear my session override and restore product comparison for this demo session.
```
