# Project lessons

- Treat Docker Compose and other shared long-lived services as globally owned state in multi-agent work. Before starting, recreating, stopping, or rebinding a shared stack, coordinate with the root agent; prefer non-mutating source/static checks when ownership is not explicit.
- After any WebMCP cart mutation, verify every shared cart indicator—not only the detailed cart panel. Keep the workflow panel, header badge, accessible label, and checkout state synchronized from the same authoritative cart result, with browser coverage for the complete visible update.
