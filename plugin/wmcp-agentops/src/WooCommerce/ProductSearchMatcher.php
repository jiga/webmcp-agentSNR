<?php

/**
 * Deterministic in-memory matching for normalized public product facts.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WooCommerce;

final class ProductSearchMatcher
{
    /**
     * @param array<string, mixed> $facts Normalized product facts.
     * @param array<string, mixed> $input Validated search input.
     */
    public function matches(array $facts, array $input): bool
    {
        $price = isset($facts['price']) && is_numeric($facts['price']) ? (float) $facts['price'] : null;

        if (isset($input['min_price']) && (null === $price || $price < (float) $input['min_price'])) {
            return false;
        }

        if (isset($input['max_price']) && (null === $price || $price > (float) $input['max_price'])) {
            return false;
        }

        $query = isset($input['query']) && is_string($input['query']) ? $input['query'] : '';
        if (! $this->contains_all_terms((string) ($facts['_search_text'] ?? ''), $query)) {
            return false;
        }

        if (! $this->matches_product_intent($facts, $query)) {
            return false;
        }

        $requested_attributes = isset($input['attributes']) && is_array($input['attributes']) ? $input['attributes'] : array();
        $attributes           = isset($facts['attributes']) && is_array($facts['attributes']) ? $facts['attributes'] : array();

        foreach ($requested_attributes as $name => $expected) {
            if (! is_string($name) || ! is_string($expected)) {
                return false;
            }

            $key = $this->attribute_key($name);
            if (! array_key_exists($key, $attributes)) {
                return false;
            }

            $actual = is_array($attributes[$key]) ? implode(' ', array_map('strval', $attributes[$key])) : (string) $attributes[$key];
            if (! $this->contains_all_terms($actual, $expected)) {
                return false;
            }
        }

        return true;
    }

    public function attribute_key(string $value): string
    {
        $value = $this->normalize($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim(is_string($value) ? $value : '', '_');
    }

    private function contains_all_terms(string $haystack, string $needle): bool
    {
        $haystack = $this->normalize($haystack);
        $needle   = $this->normalize($needle);
        $terms    = array_values(array_unique(array_filter(explode(' ', $needle))));

        foreach ($terms as $term) {
            if (false === strpos($haystack, $term)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Protect category and waterproof intent from descriptive negations such as
     * “not a backpack” or “water-resistant rather than waterproof.”
     *
     * @param array<string, mixed> $facts Normalized product facts.
     */
    private function matches_product_intent(array $facts, string $query): bool
    {
        $query = $this->normalize($query);
        if (1 === preg_match('/\bbackpacks?\b/', $query)) {
            $categories = isset($facts['_category_names']) && is_array($facts['_category_names'])
                ? implode(' ', array_map('strval', $facts['_category_names']))
                : '';
            if (1 !== preg_match('/\bbackpacks?\b/', $this->normalize($categories))) {
                return false;
            }
        }

        if (1 === preg_match('/\bwaterproof\b/', $query)) {
            $attributes = isset($facts['attributes']) && is_array($facts['attributes'])
                ? $facts['attributes']
                : array();
            $rating = isset($attributes['water_rating']) ? (string) $attributes['water_rating'] : '';

            if (1 === preg_match('/ipx\s*([0-9])/i', $rating, $matches)) {
                return (int) $matches[1] >= 4;
            }

            return false !== stripos($rating, 'waterproof');
        }

        return true;
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = wp_strip_all_tags($value, true);

        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }

        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return trim(is_string($value) ? preg_replace('/\s+/', ' ', $value) ?? '' : '');
    }
}
