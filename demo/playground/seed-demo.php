<?php

/**
 * WordPress Playground entry point for the canonical idempotent demo seeder.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

use WPWebMCP\AgentOps\Demo\Seeder;

if (! function_exists('wmcp_agentops_seed_playground_demo')) {
    /**
     * Seed and verify the disposable Playground site.
     *
     * The product/page definitions remain owned by the plugin's Seeder class so
     * Docker, WP-CLI, and Playground cannot drift into different demo datasets.
     *
     * @return array<string, mixed>
     */
    function wmcp_agentops_seed_playground_demo(): array
    {
        if (! class_exists(Seeder::class)) {
            throw new RuntimeException('Agent SNR must be active before seeding.');
        }

        $result = (new Seeder())->seed();

        global $wp_rewrite;
        if ($wp_rewrite instanceof WP_Rewrite) {
            $wp_rewrite->set_permalink_structure('/%postname%/');
        } else {
            update_option('permalink_structure', '/%postname%/');
        }
        flush_rewrite_rules(false);

        $seeded_pages = is_array($result['pages'] ?? null) ? $result['pages'] : array();
        foreach (array('landing', 'storefront', 'agentops', 'health', 'returns', 'cart', 'checkout') as $page_key) {
            if (empty($seeded_pages[$page_key])) {
                throw new RuntimeException('The demo page result is missing: ' . $page_key);
            }
        }

        foreach ($seeded_pages as $page_key => $page_id) {
            $page_id = (int) $page_id;
            $page    = get_post($page_id);
            if (! $page instanceof WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status) {
                throw new RuntimeException('The demo page was not seeded: ' . $page_key);
            }
        }

        $product_count = (int) ($result['products_created'] ?? 0) + (int) ($result['products_updated'] ?? 0);
        if (12 !== $product_count) {
            throw new RuntimeException('The canonical twelve-product demo catalog was not seeded.');
        }

        if ((int) get_option('page_on_front', 0) !== (int) $seeded_pages['landing']) {
            throw new RuntimeException('The judge landing page was not set as the front page.');
        }

        if (true !== (bool) get_option('wmcp_agentops_enabled', false)) {
            throw new RuntimeException('The demo WebMCP layer was not enabled.');
        }

        return $result;
    }
}
