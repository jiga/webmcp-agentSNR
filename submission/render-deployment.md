# Render deployment runbook

This runbook deploys the frozen Agent SNR repository as one public WordPress service and one private MariaDB service. The checked-in [`render.yaml`](../render.yaml) creates both services from Dockerfiles in this repository; it does not pull application code from a mutable external repository.

## Before provisioning

1. Push the exact release commit to `https://github.com/jiga/webmcp-agentSNR` and confirm that repository is public. The Render Blueprint and Devpost source link must resolve without account access.
2. Sign in to the intended Render workspace and redeem the hackathon credit under **Billing** before creating services. A credit or redemption code belongs only in Render's billing interface—never in Git, `render.yaml`, an environment variable, a build log, or a Devpost field.
3. Confirm the credit appears in that workspace. Credits are applied to eligible charges at billing time; they do not change the service plan selected by the Blueprint.
4. Confirm the workspace has an accepted payment method if Render requests one. The Blueprint selects two paid `1c-2g` services plus 11 GB of persistent storage. At prices checked September 3, 2026, that is approximately $52.75 for a full month before bandwidth or other usage, prorated for partial months. The amount Render shows before confirmation is authoritative, and any amount beyond the credit is the account owner's responsibility.
5. Resolve every unchecked identity, rights, URL, and submission item in [`devpost-rules-checklist.md`](devpost-rules-checklist.md). Deployment does not satisfy those declarations by itself.

Useful official references: [Blueprints](https://render.com/docs/infrastructure-as-code), [Blueprint fields](https://render.com/docs/blueprint-spec), [credits](https://render.com/docs/credits), [persistent disks](https://render.com/docs/disks), and [WordPress on Render](https://render.com/docs/deploy-wordpress).

## Create the Blueprint

1. In the Render Dashboard, choose **New → Blueprint**.
2. Connect the public Git provider repository that contains this file and select its frozen release branch.
3. Keep the Blueprint path as `render.yaml`.
4. Review the plan before applying it. It must show exactly:

   - `agent-snr`: public Docker web service, Oregon, `1c-2g`, one instance, 1 GB disk mounted at `/var/www/html/wp-content/uploads`.
   - `agent-snr-mariadb`: private Docker service, Oregon, `1c-2g`, one instance, 10 GB disk mounted at `/var/lib/mysql`.
   - Preview environments disabled and automatic deploys disabled.

5. Apply the Blueprint. Do not paste the sponsor credit, API keys, WordPress passwords, or database passwords into Blueprint environment values. Render generates the database passwords, WordPress administrator password, and eight WordPress authentication secrets.
6. Wait for MariaDB to become available and for the web service to report a successful deploy. The web container has a bounded database wait and will fail visibly instead of starting a partially configured site.

The initial web startup installs WordPress only when it is absent, then always reconciles the public HTTPS origin, activates the baked WooCommerce and Agent SNR versions, runs the idempotent demo seeder, disables WooCommerce coming-soon mode, sets pretty permalinks, verifies all three versions, and starts Apache. Only uploads and database data persist; WordPress core, WooCommerce, and Agent SNR are rebuilt from the frozen image.

## Retrieve the generated administrator login

Open **agent-snr → Environment** in the Render Dashboard. The username is the non-secret `WMCP_ADMIN_USER` value. Reveal and copy `WMCP_ADMIN_PASSWORD` only when needed, store it in a password manager, and do not paste it into issues, chat, screenshots, recordings, source files, or Devpost. The database and WordPress salt values do not need to be revealed.

If a custom domain replaces the default `onrender.com` origin, add `WMCP_PUBLIC_URL` to the web service with the exact HTTPS origin and no path, then choose **Save and deploy**. With no override, startup uses Render's `RENDER_EXTERNAL_URL` automatically.

## First-deploy validation

Use a signed-out browser first. Replace `<public-origin>` with the web service's HTTPS origin.

- `<public-origin>/` loads the judge landing page without a login.
- `<public-origin>/storefront-demo/` loads the agent-ready store.
- `<public-origin>/agentsnr-demo/` loads the monitoring surface.
- `<public-origin>/webmcp-health/` loads the visible readiness report.
- `<public-origin>/wp-json/wmcp-agentsnr/v1/health` returns HTTP 200 and JSON with `ok: true`, the expected WordPress/WooCommerce/plugin versions, a ready database, an enabled manifest, and an HTTPS-ready header check.
- Response headers include `Origin-Agent-Cluster: ?1` and the expected same-origin WebMCP permissions policy.
- The WordPress admin lists WordPress 7.1, WooCommerce 11.0.1, and Agent SNR 0.1.0; both plugins are active.
- A second manual deploy succeeds without creating duplicate demo pages, products, or categories.

Then complete the required real-client checks in the latest ChatGPT in-app browser or Chrome 149+ with WebMCP enabled. Follow [`testing-instructions.md`](testing-instructions.md), record the exact frozen commit and public URL, and update the submission artifacts only with observed results.

## Freeze for judging

The Blueprint deliberately sets `autoDeployTrigger: off`, so a source-code push does not trigger a service auto-deploy. Blueprint synchronization is separate: syncing a commit that changes `render.yaml` can still update the services. After final validation:

1. Record the deployed commit SHA and public origin in the submission package.
2. Submit the same public origin to Devpost.
3. Do not sync the Blueprint, manually deploy, rotate salts, change the public URL, or mutate entrant-controlled demo code/configuration/seed content during the freeze unless recovering from an outage. Normal isolated judge-session data and bounded cleanup may continue as designed.
4. Keep both paid services, both disks, the public repository, and unrestricted judge access available through at least September 21, 2026 at 5:00 p.m. Pacific Time.
5. Keep entrant-controlled submitted repository, deployed code/configuration/seed content, video, and Devpost entry unchanged until winners are announced on or around September 23, 2026 at 2:00 p.m. Pacific Time.
6. Make post-deadline development in a fork or separate branch that is not deployed to the submitted service.

## Recovery and shutdown

- **Bad application deploy:** manually deploy the last known-good frozen commit. The database and uploads disk remain attached, and startup reconciliation is idempotent.
- **Web service restart:** restart the same service; startup rechecks configuration and seed state before Apache starts.
- **Database recovery:** create regular logical MariaDB backups and transfer them off the service. Do not restore a Render disk snapshot for a custom database; Render warns that this can restore inconsistent database state. Restore a verified logical backup into a replacement MariaDB service instead.
- **Disk capacity:** monitor both disks. Render allows increases but not decreases.
- **After the required availability window:** export any evidence and logical database backup first, then suspend or delete paid resources from the Render Dashboard to stop future charges. Deleting a service or disk is destructive and is not performed by repository scripts.
