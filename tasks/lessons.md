# Project lessons

- Treat Docker Compose and other shared long-lived services as globally owned state in multi-agent work. Before starting, recreating, stopping, or rebinding a shared stack, coordinate with the root agent; prefer non-mutating source/static checks when ownership is not explicit.
