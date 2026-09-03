<?php

/**
 * Idempotent fictional storefront and judge-page seeder.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Demo;

use RuntimeException;
use WC_Product_Simple;
use WP_Error;

final class Seeder
{
    /**
     * @return array<string, mixed>
     */
    public function seed(): array
    {
        if (! DemoMode::enabled()) {
            throw new RuntimeException('Demo seeding requires WMCP_AGENTSNR_DEMO_MODE=true.');
        }

        if (! class_exists('WooCommerce') || ! class_exists('WC_Product_Simple')) {
            throw new RuntimeException('WooCommerce must be active before demo seeding.');
        }

        update_option('wmcp_agentsnr_enabled', true, false);
        update_option('blogname', 'Agent SNR Demo Store');
        update_option('blogdescription', 'Field-tested gear with Agent SNR outcome monitoring');
        update_option('woocommerce_currency', 'USD');
        update_option('woocommerce_calc_taxes', 'no');
        update_option('woocommerce_ship_to_countries', 'disabled');
        update_option('woocommerce_enable_guest_checkout', 'yes');
        update_option('woocommerce_enable_signup_and_login_from_checkout', 'no');

        $pages = $this->seed_pages();
        $terms = $this->seed_categories();

        $created = 0;
        $updated = 0;
        foreach ($this->products() as $product_data) {
            $was_created = $this->seed_product($product_data, $terms);
            $was_created ? ++$created : ++$updated;
        }

        update_option('wmcp_agentsnr_store_policies', $this->policies($pages), false);

        update_option('show_on_front', 'page');
        update_option('page_on_front', $pages['landing']);
        update_option('woocommerce_cart_page_id', $pages['cart']);
        update_option('woocommerce_checkout_page_id', $pages['checkout']);
        update_option('wmcp_agentsnr_pages', $pages, false);

        flush_rewrite_rules(false);

        return array(
            'pages'            => $pages,
            'products_created' => $created,
            'products_updated' => $updated,
        );
    }

    /**
     * @return array<string, int>
     */
    private function seed_pages(): array
    {
        $definitions = array(
            'landing'    => array('Agent SNR — Overview', 'webmcp-field-lab', '[wmcp_judge_landing]'),
            'storefront' => array('Agent-ready Storefront', 'storefront-demo', '[wmcp_storefront_demo]'),
            'agentsnr'   => array('Agent SNR — Monitor', 'agentsnr-demo', '[wmcp_agentsnr_demo]'),
            'health'     => array('WebMCP Readiness', 'webmcp-health', '[wmcp_health]'),
            'returns'    => array(
                'Returns policy',
                'returns-policy',
                'Unused products in original condition may be returned within 30 days. Products explicitly marked with a longer return window retain that longer window. Final-sale items are excluded; no demo products are final sale.',
            ),
            'shipping'   => array(
                'Shipping policy',
                'shipping-policy',
                'Demo orders use deterministic free shipping within the United States and normally dispatch within two business days. No physical demo shipment is sent.',
            ),
            'warranty'   => array(
                'Warranty policy',
                'warranty-policy',
                'The demo catalog includes a fictional 365-day workmanship warranty. This demonstration does not create a real warranty claim.',
            ),
            'privacy'    => array(
                'Privacy policy',
                'privacy-policy',
                'The Agent SNR demo stores allowlisted operational events and one-way session hashes. It does not store raw prompts, identities, addresses, cookies, nonces, authorization headers, or payment data.',
            ),
            'cart'       => array('Cart', 'cart', '[woocommerce_cart]'),
            'checkout'   => array('Checkout', 'checkout', '[woocommerce_checkout]'),
        );

        $pages = array();
        foreach ($definitions as $key => [$title, $slug, $content]) {
            $existing = get_page_by_path($slug, OBJECT, 'page');
            $post     = array(
                'ID'           => $existing instanceof \WP_Post ? $existing->ID : 0,
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
            );

            $page_id = wp_insert_post(wp_slash($post), true);
            if ($page_id instanceof WP_Error) {
                throw new RuntimeException('Unable to seed a required demo page.');
            }

            update_post_meta($page_id, '_wmcp_demo_page', $key);
            $pages[$key] = (int) $page_id;
        }

        return $pages;
    }

    /**
     * @param array<string, int> $pages Seeded page IDs.
     * @return array<string, array<string, mixed>>
     */
    private function policies(array $pages): array
    {
        return array(
            'returns'  => array(
                'page_id'            => $pages['returns'],
                'facts'              => array(
                    'return_days'                 => 30,
                    'longer_marked_window_applies' => true,
                    'final_sale_excluded'          => true,
                ),
                'effective_date'     => '2026-08-29',
                'evidence_excerpt'   => 'Unused products in original condition may be returned within 30 days. Products marked with a longer window retain it.',
                'product_exceptions' => array(),
            ),
            'shipping' => array(
                'page_id'            => $pages['shipping'],
                'facts'              => array('shipping_regions' => array('US'), 'dispatch_days' => 2),
                'effective_date'     => '2026-08-29',
                'evidence_excerpt'   => 'Demo orders use deterministic free shipping within the United States and normally dispatch within two business days.',
                'product_exceptions' => array(),
            ),
            'warranty' => array(
                'page_id'            => $pages['warranty'],
                'facts'              => array('warranty_days' => 365),
                'effective_date'     => '2026-08-29',
                'evidence_excerpt'   => 'Demo products include a fictional 365-day workmanship warranty.',
                'product_exceptions' => array(),
            ),
            'privacy'  => array(
                'page_id'            => $pages['privacy'],
                'facts'              => array(),
                'effective_date'     => '2026-08-29',
                'evidence_excerpt'   => 'The demo stores allowlisted operational events and one-way session hashes, never raw prompts or personal checkout data.',
                'product_exceptions' => array(),
            ),
        );
    }

    /**
     * @return array<string, int>
     */
    private function seed_categories(): array
    {
        $terms = array();
        foreach (array('backpacks' => 'Backpacks', 'accessories' => 'Accessories') as $slug => $name) {
            $term = term_exists($slug, 'product_cat');
            if (! $term) {
                $term = wp_insert_term($name, 'product_cat', array('slug' => $slug));
            }

            if ($term instanceof WP_Error) {
                throw new RuntimeException('Unable to seed a required product category.');
            }

            $terms[$slug] = (int) (is_array($term) ? $term['term_id'] : $term);
        }

        return $terms;
    }

    /**
     * @param array<string, mixed> $data Product definition.
     * @param array<string, int>   $terms Product category IDs.
     */
    private function seed_product(array $data, array $terms): bool
    {
        $existing_id = wc_get_product_id_by_sku((string) $data['sku']);
        $product     = $existing_id ? wc_get_product($existing_id) : new WC_Product_Simple();
        $created     = ! $existing_id;

        if (! $product instanceof WC_Product_Simple) {
            throw new RuntimeException('A reserved demo SKU belongs to a non-simple product.');
        }

        $product->set_name((string) $data['name']);
        $product->set_slug((string) $data['slug']);
        $product->set_sku((string) $data['sku']);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_regular_price((string) $data['price']);
        $product->set_price((string) $data['price']);
        $product->set_manage_stock(false);
        $product->set_stock_status((string) $data['stock']);
        $product->set_weight((string) $data['weight']);
        $product->set_virtual(false);
        $product->set_description((string) $data['description']);
        $product->set_short_description((string) $data['short_description']);
        $product->set_category_ids(array($terms[(string) $data['category']]));
        $product->save();

        $product->update_meta_data('_wmcp_water_rating', $data['water_rating']);
        $product->update_meta_data('_wmcp_capacity_liters', $data['capacity']);
        $product->update_meta_data('_wmcp_laptop_inches', $data['laptop']);
        $product->update_meta_data('_wmcp_return_days', $data['return_days']);
        $product->update_meta_data('_wmcp_material', $data['material']);
        $product->update_meta_data('_wmcp_colors_json', wp_json_encode($data['colors']));
        $product->update_meta_data('_wmcp_demo_product', 'yes');
        $product->update_meta_data('_wmcp_demo_image', 'assets/images/products/' . $data['slug'] . '.svg');
        $product->save();

        return $created;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(): array
    {
        return array(
            $this->product('alpineflow-24', 'AlpineFlow 24 Pack', 109, 'IPX6', 24, 1.00, 15, 45, 'instock', 'backpacks', 'Recycled ripstop', array('moss', 'ember', 'blue'), 'A sealed 24-liter field pack with balanced capacity and weather protection.'),
            $this->product('raintrail-20', 'RainTrail 20 Pack', 89, 'IPX4', 20, 0.78, 14, 30, 'instock', 'backpacks', 'Coated nylon', array('slate', 'blue'), 'A light rain-ready day pack and the lowest-priced comparison candidate.'),
            $this->product('coastrunner-26', 'CoastRunner 26 Pack', 119, 'IPX5', 26, 1.05, 15, 30, 'instock', 'backpacks', 'Ocean-bound ripstop', array('kelp', 'sand'), 'The highest-capacity waterproof candidate under the supplied budget.'),
            $this->product('summitshell-32', 'SummitShell 32 Pack', 139, 'IPX6', 32, 1.20, 16, 45, 'instock', 'backpacks', 'Ballistic recycled nylon', array('basalt'), 'A larger expedition pack above the demonstration budget.'),
            $this->product('urbandry-18', 'UrbanDry 18 Pack', 79, 'Water-resistant', 18, 0.70, 13, 30, 'instock', 'backpacks', 'Waxed canvas', array('charcoal', 'ochre'), 'A compact commuter bag that is water-resistant rather than waterproof.'),
            $this->product('canyonday-22', 'CanyonDay 22 Pack', 99, 'IPX3', 22, 0.85, 14, 14, 'instock', 'backpacks', 'Robic nylon', array('clay'), 'A breathable day pack whose return window and water rating miss the prompt threshold.'),
            $this->product('ridgeline-28', 'RidgeLine 28 Pack', 129, 'IPX5', 28, 1.10, 16, 30, 'instock', 'backpacks', 'Recycled Cordura', array('pine', 'black'), 'A technical 28-liter pack priced just above the demonstration budget.'),
            $this->product('harborlite-16', 'HarborLite 16 Pack', 69, 'IPX4', 16, 0.62, 13, 30, 'instock', 'backpacks', 'Coated mini-ripstop', array('fog', 'blue'), 'A small low-price waterproof alternative for light loads.'),
            $this->product('terraroll-25', 'TerraRoll 25 Pack', 115, 'IPX4', 25, 0.95, 15, 30, 'outofstock', 'backpacks', 'Recycled roll-top nylon', array('blue'), 'An out-of-stock roll-top used to demonstrate honest availability filtering.'),
            $this->product('switchback-sling', 'Switchback Sling', 49, 'IPX3', 8, 0.35, null, 30, 'instock', 'accessories', 'Coated nylon', array('moss', 'black'), 'An eight-liter sling for short trail loops.'),
            $this->product('drypod-organizer', 'DryPod Organizer', 29, 'IPX6', 4, 0.20, null, 45, 'instock', 'accessories', 'Welded TPU', array('ember', 'blue'), 'A sealed organizer for electronics and small essentials.'),
            $this->product('trailcover-rain-shell', 'TrailCover Rain Shell', 25, 'Waterproof cover', null, 0.15, null, 30, 'instock', 'accessories', 'Silicone-coated nylon', array('safety orange'), 'A pack rain cover, intentionally classified as an accessory rather than a backpack.'),
        );
    }

    /**
     * @param list<string> $colors Public colors.
     * @return array<string, mixed>
     */
    private function product(
        string $slug,
        string $name,
        float $price,
        string $water_rating,
        ?int $capacity,
        float $weight,
        ?int $laptop,
        int $return_days,
        string $stock,
        string $category,
        string $material,
        array $colors,
        string $description
    ): array {
        return array(
            'slug'              => $slug,
            'sku'               => 'wmcp-' . $slug,
            'name'              => $name,
            'price'             => $price,
            'water_rating'      => $water_rating,
            'capacity'          => $capacity,
            'weight'            => $weight,
            'laptop'            => $laptop,
            'return_days'       => $return_days,
            'stock'             => $stock,
            'category'          => $category,
            'material'          => $material,
            'colors'            => $colors,
            'description'       => $description,
            'short_description' => $description,
        );
    }
}
