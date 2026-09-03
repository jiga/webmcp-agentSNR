Agent SNR — WordPress Playground bundle

This disposable site runs WordPress 7.1 on PHP 8.3, downloads and activates
WooCommerce 11.0.1, installs the bundled Agent SNR plugin ZIP, invokes
the plugin's canonical idempotent demo seeder, logs in as the Playground admin,
and opens the judge landing page.

Important WebMCP limitation
---------------------------
WordPress Playground normally renders the WordPress site in an iframe, while
this project intentionally registers tools only in a top-level browsing context.
The plugin therefore reports an "embedded-context" diagnostic and deliberately
skips direct tool registration here. Use the normal top-level HTTPS WordPress
demo for the official WebMCP judging path. This bundle proves installability,
SQLite portability, seeded human storefront behavior, and access to the
WordPress admin dashboard.

No payment processor, API key, external account, or secret is required. The
WooCommerce demo payment method never contacts a payment processor.
