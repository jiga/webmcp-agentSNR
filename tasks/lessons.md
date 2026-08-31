# Project lessons

- Treat Docker Compose and other shared long-lived services as globally owned state in multi-agent work. Before starting, recreating, stopping, or rebinding a shared stack, coordinate with the root agent; prefer non-mutating source/static checks when ownership is not explicit.
- After any WebMCP cart mutation, verify every shared cart indicator—not only the detailed cart panel. Keep the workflow panel, header badge, accessible label, and checkout state synchronized from the same authoritative cart result, with browser coverage for the complete visible update.
- For every clickable workflow row, test the largest realistic replay—not only short fixtures. Bound server payloads before the transport limit, disclose truncation in the UI, and surface a visible row-level error instead of leaving an action that appears to do nothing.
- Treat WooCommerce extension filters as heterogeneous integration boundaries. Preserve third-party keys and object instances, avoid string-only whole-array transforms such as default `array_unique()`, and regression-test mixed class-string/object inputs with representative payment extensions.
