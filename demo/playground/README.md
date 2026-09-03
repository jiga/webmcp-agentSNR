# WordPress Playground bundle

The release bundle is a private, disposable WordPress reproduction. It pins WordPress 7.1 and PHP 8.3, enables Playground networking, installs WooCommerce 11.0.1 from the official WordPress download service, installs the exact built plugin ZIP, calls the plugin's canonical idempotent `Seeder`, logs in as the Playground administrator, and opens the judge landing page.

Build it from the repository root:

```bash
./bin/build-playground-bundle.sh
```

The versioned bundle and SHA-256 sidecar are written to `dist/`. The archive has this root-level layout:

```text
blueprint.json
README.txt
seed-demo.php
wmcp-agentsnr.zip
wmcp-agentsnr.zip.sha256
```

Run the built bundle locally with the pinned CLI used by CI:

```bash
npx --yes @wp-playground/cli@3.1.51 run-blueprint \
  --blueprint=dist/wmcp-agentsnr-playground-0.1.0.zip
```

After publishing the ZIP at a stable public URL with `Access-Control-Allow-Origin: *`, open it through:

```text
https://playground.wordpress.net/?blueprint-url=<URL-ENCODED-PUBLIC-BUNDLE-URL>
```

## Iframe diagnostic is intentional

WordPress Playground normally embeds WordPress in an iframe, while this project intentionally registers tools only in a top-level browsing context. The plugin therefore reports `embedded-context` and skips direct tool registration in the ordinary Playground UI. This is not a failed install: Playground verifies the human storefront, WordPress admin, SQLite portability, packaging, activation, and seeding. Use the normal top-level HTTPS WordPress deployment for the official WebMCP judging path.

The source `blueprint.json` references `/wmcp-agentsnr.zip`; that resource is injected only into the built bundle. CI validates the archive layout and executes the complete Blueprint with the official Playground CLI.
