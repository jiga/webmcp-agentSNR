FROM wordpress:cli-2.12-php8.3@sha256:2b5e9d4d3e51909dca1aaa4732e9f5e5bf0377c2114dbd8ff39f060bff202586 AS wp-cli

FROM wordpress:7.1-php8.3-apache@sha256:5a93c470ae8220fddf71f6ebe3bc94e615ddc2ae4d9810f795b830fb11c41a17

ARG WOOCOMMERCE_VERSION=11.0.1
ARG WOOCOMMERCE_SHA256=da189b6616c610d15a2106f93151dab81b78f83e075bcefce221ac0d00b4fa21
ARG RENDER_GIT_COMMIT=unknown

LABEL org.opencontainers.image.revision="${RENDER_GIT_COMMIT}"

ENV WMCP_WORDPRESS_VERSION=7.1 \
    WMCP_WOOCOMMERCE_VERSION=11.0.1 \
    WMCP_AGENTOPS_VERSION=0.1.0

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip; \
    rm -rf /var/lib/apt/lists/*; \
    curl --fail --location --silent --show-error --proto '=https' --tlsv1.2 --retry 3 \
        "https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip" \
        --output /tmp/woocommerce.zip; \
    echo "${WOOCOMMERCE_SHA256}  /tmp/woocommerce.zip" | sha256sum --check --strict; \
    unzip -q /tmp/woocommerce.zip -d /usr/src/wordpress/wp-content/plugins; \
    rm /tmp/woocommerce.zip; \
    chown -R www-data:www-data /usr/src/wordpress/wp-content/plugins/woocommerce

COPY --from=wp-cli /usr/local/bin/wp /usr/local/bin/wp
COPY --chown=www-data:www-data plugin/wmcp-agentops /usr/src/wordpress/wp-content/plugins/wmcp-agentops
COPY --chown=www-data:www-data LICENSE THIRD_PARTY_NOTICES.md /usr/src/wordpress/wp-content/plugins/wmcp-agentops/
COPY --chown=www-data:www-data bin/seed-demo.php /opt/agent-snr/bin/seed-demo.php
COPY --chmod=0755 deploy/render/render-entrypoint.sh /usr/local/bin/agent-snr-render-entrypoint

ENTRYPOINT ["/usr/local/bin/agent-snr-render-entrypoint"]
CMD ["apache2-foreground"]
