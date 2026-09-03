<?php

/**
 * Deterministic in-memory matching for normalized public product facts.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

final class ProductSearchMatcher
{
    private const MAX_PRICE_PATTERN = '/\b(?:under|below|up\s+to|at\s+most|max(?:imum)?|less\s+than|no\s+more\s+than)\s+(?:usd\s+)?(\d+)(?:\s+(\d{1,2}))?\b/u';
    private const MIN_PRICE_PATTERN = '/\b(?:over|above|at\s+least|min(?:imum)?|more\s+than|no\s+less\s+than)\s+(?:usd\s+)?(\d+)(?:\s+(\d{1,2}))?\b/u';

    /**
     * @param array<string, mixed> $facts Normalized product facts.
     * @param array<string, mixed> $input Validated search input.
     */
    public function matches(array $facts, array $input): bool
    {
        $query        = isset($input['query']) && is_string($input['query']) ? $input['query'] : '';
        $query_bounds = $this->query_price_bounds($query);
        $min_price    = isset($input['min_price']) ? (float) $input['min_price'] : null;
        $max_price    = isset($input['max_price']) ? (float) $input['max_price'] : null;
        if (null !== $query_bounds['min']) {
            $min_price = null === $min_price ? $query_bounds['min'] : max($min_price, $query_bounds['min']);
        }
        if (null !== $query_bounds['max']) {
            $max_price = null === $max_price ? $query_bounds['max'] : min($max_price, $query_bounds['max']);
        }
        if (null !== $min_price && null !== $max_price && $min_price > $max_price) {
            return false;
        }

        $price = isset($facts['price']) && is_numeric($facts['price']) ? (float) $facts['price'] : null;
        if (null !== $min_price && (null === $price || $price < $min_price)) {
            return false;
        }

        if (null !== $max_price && (null === $price || $price > $max_price)) {
            return false;
        }

        $text_query = $this->product_terms($query, $input, $query_bounds);
        if (! $this->contains_all_terms((string) ($facts['_search_text'] ?? ''), $text_query)) {
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

    /**
     * Remove only constraints already represented by dedicated input fields.
     * Agents often repeat those values in natural-language query text; treating
     * words such as "under" or "in stock" as product-name terms creates false
     * zero-result signals even though the structured filters are valid.
     *
     * @param array<string, mixed>              $input  Validated search input.
     * @param array{min:?float,max:?float} $query_bounds Parsed textual price bounds.
     */
    private function product_terms(string $query, array $input, array $query_bounds): string
    {
        $terms = $this->normalize($query);

        if (isset($input['max_price']) || null !== $query_bounds['max']) {
            $terms = (string) preg_replace(self::MAX_PRICE_PATTERN, ' ', $terms);
        }
        if (isset($input['min_price']) || null !== $query_bounds['min']) {
            $terms = (string) preg_replace(self::MIN_PRICE_PATTERN, ' ', $terms);
        }
        if (true === ($input['in_stock_only'] ?? null)) {
            $terms = (string) preg_replace('/\b(?:in stock|currently available|available now)\b/u', ' ', $terms);
        }

        $attributes = isset($input['attributes']) && is_array($input['attributes'])
            ? $input['attributes']
            : array();
        foreach ($attributes as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                continue;
            }
            $value = $this->normalize($value);
            if ('' !== $value) {
                $quoted = preg_quote($value, '/');
                if ('water_rating' === $this->attribute_key($name)) {
                    $terms = (string) preg_replace(
                        '/\b(?:with\s+)?(?:at\s+least\s+)?(?:water\s+rating\s+)?' . $quoted . '(?:\s+(?:protection|or\s+better))?\b/u',
                        ' ',
                        $terms
                    );
                } else {
                    $terms = (string) preg_replace('/\b' . $quoted . '\b/u', ' ', $terms);
                }
            }
        }

        return trim((string) preg_replace('/\s+/', ' ', $terms));
    }

    /** @return array{min:?float,max:?float} */
    private function query_price_bounds(string $query): array
    {
        $query = $this->normalize($query);

        return array(
            'min' => $this->price_bound($query, self::MIN_PRICE_PATTERN),
            'max' => $this->price_bound($query, self::MAX_PRICE_PATTERN),
        );
    }

    private function price_bound(string $query, string $pattern): ?float
    {
        if (1 !== preg_match($pattern, $query, $matches)) {
            return null;
        }

        return (float) ($matches[1] . (isset($matches[2]) && '' !== $matches[2] ? '.' . $matches[2] : ''));
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
