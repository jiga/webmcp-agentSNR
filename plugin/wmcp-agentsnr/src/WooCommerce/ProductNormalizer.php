<?php

/**
 * Allowlisted compact public product facts.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

final class ProductNormalizer
{
    /** @var array<string, string> */
    public const DEMO_META = array(
        '_wmcp_water_rating'    => 'water_rating',
        '_wmcp_capacity_liters' => 'capacity_liters',
        '_wmcp_laptop_inches'   => 'laptop_inches',
        '_wmcp_return_days'     => 'return_days',
        '_wmcp_material'        => 'material',
        '_wmcp_colors_json'     => 'colors',
        '_wmcp_demo_product'    => 'demo_product',
    );

    public function is_public(object $product): bool
    {
        if (! method_exists($product, 'get_status') || 'publish' !== (string) $product->get_status()) {
            return false;
        }

        if (! method_exists($product, 'get_catalog_visibility') || 'visible' !== (string) $product->get_catalog_visibility()) {
            return false;
        }

        if (method_exists($product, 'is_visible') && ! $product->is_visible()) {
            return false;
        }

        if (
            function_exists('post_password_required')
            && method_exists($product, 'get_id')
            && post_password_required((int) $product->get_id())
        ) {
            return false;
        }

        return ! (method_exists($product, 'is_type') && $product->is_type('variation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(object $product): array
    {
        $attributes  = $this->attributes($product);
        $return_days = isset($attributes['return_days']) && is_numeric($attributes['return_days'])
            ? (int) $attributes['return_days']
            : null;
        $price       = method_exists($product, 'get_price') && is_numeric($product->get_price())
            ? (float) $product->get_price()
            : 0.0;
        $stock       = method_exists($product, 'get_stock_status') ? (string) $product->get_stock_status() : 'outofstock';
        $purchasable = method_exists($product, 'is_purchasable') && (bool) $product->is_purchasable();
        $in_stock    = method_exists($product, 'is_in_stock') && (bool) $product->is_in_stock();
        $currency    = function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '';
        $url         = method_exists($product, 'get_permalink') ? (string) $product->get_permalink() : '';
        $name        = method_exists($product, 'get_name') ? $this->text((string) $product->get_name(), 160) : '';
        $slug        = method_exists($product, 'get_slug') ? (string) $product->get_slug() : '';

        $category_names = $this->category_names($product);
        $facts          = array(
            'id'           => method_exists($product, 'get_id') ? (int) $product->get_id() : 0,
            'name'         => $name,
            'url'          => $url,
            'price'        => $price,
            'currency'     => strtoupper($currency),
            'stock_status' => $stock,
            'purchasable'  => $purchasable && $in_stock,
            'attributes'   => $attributes,
            'return_days'  => $return_days,
            'evidence'     => $this->evidence($attributes, $price, $stock),
        );

        $facts['_search_text'] = implode(
            ' ',
            array_filter(
                array(
                    $name,
                    $slug,
                    $this->short_description($product),
                    implode(' ', $category_names),
                    $this->flatten_attributes($attributes),
                    $this->water_search_aliases((string) ($attributes['water_rating'] ?? '')),
                )
            )
        );
        $facts['_category_names'] = $category_names;

        return $facts;
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(object $product): array
    {
        $facts                      = $this->summary($product);
        $facts['slug']              = method_exists($product, 'get_slug') ? (string) $product->get_slug() : '';
        $facts['short_description'] = $this->short_description($product);
        $facts['images']            = $this->images($product);

        return $facts;
    }

    /**
     * @param array<string, mixed> $facts Product facts.
     * @return array<string, mixed>
     */
    public function without_internal(array $facts): array
    {
        unset($facts['_search_text'], $facts['_category_names']);

        return $facts;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(object $product): array
    {
        $result = array();

        if (method_exists($product, 'get_attributes')) {
            foreach ((array) $product->get_attributes() as $attribute) {
                if (! is_object($attribute) || ! method_exists($attribute, 'get_name')) {
                    continue;
                }

                if (method_exists($attribute, 'get_visible') && ! $attribute->get_visible()) {
                    continue;
                }

                $name  = (string) $attribute->get_name();
                $key   = $this->key($name);
                $value = method_exists($product, 'get_attribute') ? (string) $product->get_attribute($name) : '';

                if ('' !== $key && '' !== trim($value)) {
                    $result[$key] = $this->text($value, 180);
                }
            }
        }

        $this->add_measurement($result, 'weight', $product, 'get_weight');
        $this->add_measurement($result, 'length', $product, 'get_length');
        $this->add_measurement($result, 'width', $product, 'get_width');
        $this->add_measurement($result, 'height', $product, 'get_height');

        foreach (self::DEMO_META as $meta_key => $output_key) {
            if (! method_exists($product, 'get_meta')) {
                break;
            }

            $value = $product->get_meta($meta_key, true);
            if ('' === $value || null === $value) {
                continue;
            }

            if ('colors' === $output_key) {
                $colors = is_array($value) ? $value : json_decode((string) $value, true);
                if (is_array($colors)) {
                    $result[$output_key] = array_values(
                        array_slice(
                            array_filter(array_map(fn ($color): string => $this->text((string) $color, 40), $colors)),
                            0,
                            12
                        )
                    );
                }
                continue;
            }

            if (in_array($output_key, array('capacity_liters', 'laptop_inches', 'return_days'), true) && is_numeric($value)) {
                $result[$output_key] = 'return_days' === $output_key ? (int) $value : (float) $value;
                continue;
            }

            if ('demo_product' === $output_key) {
                $result[$output_key] = in_array(strtolower((string) $value), array('1', 'yes', 'true'), true);
                continue;
            }

            $result[$output_key] = $this->text((string) $value, 120);
        }

        ksort($result);

        return $result;
    }

    /**
     * @param array<string, mixed> $attributes Product attributes.
     * @return list<array<string, mixed>>
     */
    private function evidence(array $attributes, float $price, string $stock): array
    {
        $evidence = array(
            array('field' => 'price', 'source' => 'woocommerce', 'value' => $price),
            array('field' => 'stock_status', 'source' => 'woocommerce', 'value' => $stock),
        );

        foreach (self::DEMO_META as $output_key) {
            if (array_key_exists($output_key, $attributes)) {
                $evidence[] = array('field' => $output_key, 'source' => 'documented_public_product_meta', 'value' => $attributes[$output_key]);
            }
        }

        return array_slice($evidence, 0, 12);
    }

    private function add_measurement(array &$attributes, string $key, object $product, string $method): void
    {
        if (! method_exists($product, $method)) {
            return;
        }

        $value = $product->{$method}();
        if ('' !== $value && null !== $value && is_numeric($value)) {
            $attributes[$key] = (float) $value;
        }
    }

    private function short_description(object $product): string
    {
        $description = method_exists($product, 'get_short_description') ? (string) $product->get_short_description() : '';

        return $this->text($description, 500);
    }

    /**
     * @return list<string>
     */
    private function category_names(object $product): array
    {
        if (! function_exists('wp_get_post_terms') || ! method_exists($product, 'get_id')) {
            return array();
        }

        $terms = wp_get_post_terms((int) $product->get_id(), 'product_cat', array('fields' => 'names'));
        if (! is_array($terms)) {
            return array();
        }

        return array_values(array_map(fn ($term): string => $this->text((string) $term, 80), $terms));
    }

    /**
     * @return list<string>
     */
    private function images(object $product): array
    {
        if (! function_exists('wp_get_attachment_image_url')) {
            return array();
        }

        $ids = array();
        if (method_exists($product, 'get_image_id')) {
            $ids[] = (int) $product->get_image_id();
        }
        if (method_exists($product, 'get_gallery_image_ids')) {
            $ids = array_merge($ids, array_map('intval', (array) $product->get_gallery_image_ids()));
        }

        $urls = array();
        foreach (array_unique(array_filter($ids)) as $id) {
            $url = wp_get_attachment_image_url($id, 'woocommerce_single');
            if (is_string($url) && '' !== $url) {
                $urls[] = $url;
            }
            if (3 <= count($urls)) {
                break;
            }
        }

        return $urls;
    }

    /**
     * @param array<string, mixed> $attributes Attributes.
     */
    private function flatten_attributes(array $attributes): string
    {
        $parts = array();
        foreach ($attributes as $name => $value) {
            $parts[] = (string) $name;
            $parts[] = is_array($value) ? implode(' ', array_map('strval', $value)) : (string) $value;
        }

        return implode(' ', $parts);
    }

    private function water_search_aliases(string $rating): string
    {
        if ('' === $rating) {
            return '';
        }

        $normalized = strtolower($rating);
        if (false !== strpos($normalized, 'ipx') || false !== strpos($normalized, 'waterproof')) {
            return 'water waterproof water-resistant water resistant';
        }

        return 'water resistant';
    }

    private function key(string $value): string
    {
        $value = preg_replace('/^pa_/', '', strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', is_string($value) ? $value : '');

        return trim(is_string($value) ? $value : '', '_');
    }

    private function text(string $value, int $limit): string
    {
        if (function_exists('strip_shortcodes')) {
            $value = strip_shortcodes($value);
        }

        $value = wp_strip_all_tags($value, true);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
    }
}
