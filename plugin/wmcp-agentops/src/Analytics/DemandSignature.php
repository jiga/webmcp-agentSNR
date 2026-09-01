<?php

/**
 * Privacy-safe canonical demand signatures for aggregate opportunity signals.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Analytics;

use WPWebMCP\AgentOps\Support\Json;

final class DemandSignature
{
    private const PUBLIC_TERMS = array(
        'backpack',
        'backpacks',
        'bag',
        'compact',
        'commuter',
        'lightweight',
        'organizer',
        'pack',
        'rain',
        'sling',
        'waterproof',
    );

    private const PUBLIC_ATTRIBUTE_KEYS = array(
        'capacity_liters',
        'laptop_inches',
        'size',
        'water_rating',
    );

    private const DEMO_CATEGORY_SLUGS = array('backpacks');

    /**
     * @param array<string, mixed> $input Search input.
     * @param array<string, mixed> $result Search result.
     * @return array{key:string,title:string,context:array<string,mixed>}
     */
    public function from_search(array $input, array $result): array
    {
        $query = isset($input['query']) && is_string($input['query']) ? $this->normalize($input['query']) : '';
        $terms = array_values(
            array_intersect(
                array_values(array_unique(array_filter(explode(' ', $query)))),
                $this->public_terms()
            )
        );
        if (in_array('backpacks', $terms, true) && ! in_array('backpack', $terms, true)) {
            $terms[] = 'backpack';
        }
        $terms = array_values(array_filter($terms, static fn (string $term): bool => 'backpacks' !== $term && 'pack' !== $term));
        sort($terms, SORT_STRING);

        $categories = array();
        foreach (array_slice((array) ($input['categories'] ?? array()), 0, 5) as $category) {
            if (is_string($category)) {
                $safe = $this->slug($category);
                if ('' !== $safe && $this->public_category($safe)) {
                    $categories[] = $safe;
                }
            }
        }
        $categories = array_values(array_unique($categories));
        sort($categories, SORT_STRING);

        $attributes = array();
        foreach (array_slice((array) ($input['attributes'] ?? array()), 0, 8, true) as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                continue;
            }
            $safe_name  = $this->slug($name);
            if (! in_array($safe_name, self::PUBLIC_ATTRIBUTE_KEYS, true)) {
                continue;
            }
            $safe_value = $this->public_attribute_value($safe_name, $value);
            if ('' !== $safe_name && null !== $safe_value) {
                $attributes[$safe_name] = $safe_value;
            }
        }
        ksort($attributes);

        $context = array(
            'terms'         => array() === $terms ? array('other') : $terms,
            'categories'    => $categories,
            'attributes'    => $attributes,
            'price_bucket'  => $this->price_bucket($input),
            'in_stock_only' => true === ($input['in_stock_only'] ?? true),
        );
        if (array('other') === $context['terms'] && '' !== $query) {
            $context['unknown_query_key'] = hash_hmac('sha256', $query, $this->site_secret());
        }
        $key = hash('sha256', Json::encode($context));

        return array(
            'key'     => $key,
            'title'   => $this->title($context, $result),
            'context' => $context,
        );
    }

    /** @param array<string, mixed> $input Search input. */
    private function price_bucket(array $input): ?string
    {
        if (isset($input['max_price']) && is_numeric($input['max_price'])) {
            $maximum = $this->bucket(max(0, (int) ceil((float) $input['max_price'])), true);

            return 'under_' . min(1000000, $maximum);
        }
        if (isset($input['min_price']) && is_numeric($input['min_price'])) {
            $minimum = $this->bucket(max(0, (int) floor((float) $input['min_price'])), false);

            return 'over_' . min(1000000, $minimum);
        }

        return null;
    }

    /** @return list<string> */
    private function public_terms(): array
    {
        $terms = self::PUBLIC_TERMS;
        if (function_exists('get_terms')) {
            $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'names', 'number' => 200));
            if (is_array($categories)) {
                foreach ($categories as $category) {
                    foreach (explode(' ', $this->normalize((string) $category)) as $term) {
                        if (1 === preg_match('/\A[a-z][a-z0-9-]{1,31}\z/', $term)) {
                            $terms[] = $term;
                        }
                    }
                }
            }
        }
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('wmcp_agentops_public_demand_terms', $terms);
            if (is_array($filtered)) {
                $terms = $filtered;
            }
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(fn ($term): string => $this->slug((string) $term), $terms),
                    static fn (string $term): bool => '' !== $term
                )
            )
        );
    }

    private function public_category(string $slug): bool
    {
        if (in_array($slug, self::DEMO_CATEGORY_SLUGS, true)) {
            return true;
        }
        if (! function_exists('term_exists')) {
            return false;
        }

        return ! empty(term_exists($slug, 'product_cat'));
    }

    private function bucket(int $value, bool $ceiling): int
    {
        $buckets = array(25, 50, 75, 100, 150, 200, 300, 500, 1000, 2500, 5000, 10000, 100000, 1000000);
        if ($ceiling) {
            foreach ($buckets as $bucket) {
                if ($value <= $bucket) {
                    return $bucket;
                }
            }

            return 1000000;
        }
        $selected = 0;
        foreach ($buckets as $bucket) {
            if ($bucket > $value) {
                break;
            }
            $selected = $bucket;
        }

        return $selected;
    }

    /**
     * @param array<string, mixed> $context Canonical context.
     * @param array<string, mixed> $result Search result.
     */
    private function title(array $context, array $result): string
    {
        $parts = array();
        $terms = array_values(array_filter((array) $context['terms'], static fn ($term): bool => 'other' !== $term));
        if (array() !== $terms) {
            $parts[] = ucfirst(implode(' ', array_map('strval', $terms)));
        } elseif (1 === (int) ($result['result_count'] ?? 0) && isset($result['products'][0]['name'])) {
            // A merchant-authored public product name is safe evidence and is
            // more useful than retaining the visitor's arbitrary query text.
            $parts[] = sanitize_text_field((string) $result['products'][0]['name']);
        } elseif (! empty($context['categories'])) {
            $parts[] = ucfirst(str_replace('-', ' ', (string) $context['categories'][0]));
        } else {
            $parts[] = 'Product search';
        }

        $bucket = $context['price_bucket'] ?? null;
        if (is_string($bucket) && 1 === preg_match('/\Aunder_([0-9]+)\z/', $bucket, $matches)) {
            $parts[] = 'under $' . $matches[1];
        } elseif (is_string($bucket) && 1 === preg_match('/\Aover_([0-9]+)\z/', $bucket, $matches)) {
            $parts[] = 'over $' . $matches[1];
        }

        return mb_substr(implode(' · ', $parts), 0, 300);
    }

    private function public_attribute_value(string $key, string $value): ?string
    {
        $value = $this->normalize($value);
        if ('' === $value || 80 < mb_strlen($value)) {
            return null;
        }
        // Reject common direct-identifier shapes. Attribute values should be
        // public product facts, never contact or account information.
        if (str_contains($value, '@') || 1 === preg_match('/(?:https?:\/\/|www\.|\b\d{7,}\b)/i', $value)) {
            return null;
        }

        if ('water_rating' === $key) {
            return 1 === preg_match('/\A(?:ipx\s*[0-9](?:\s*(?:or|and)\s*(?:higher|above))?|waterproof|water resistant)\z/i', $value)
                ? $value
                : null;
        }
        if ('size' === $key) {
            return in_array($value, array('compact', 'small', 'medium', 'large'), true) ? $value : null;
        }
        if (in_array($key, array('capacity_liters', 'laptop_inches'), true)) {
            return 1 === preg_match('/\A[0-9]{1,3}(?:\.[0-9]{1,2})?\z/', $value) ? $value : null;
        }

        return null;
    }

    private function slug(string $value): string
    {
        $value = $this->normalize($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return mb_substr(trim(is_string($value) ? $value : '', '_'), 0, 80);
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = wp_strip_all_tags($value, true);
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}@.:\/]+/u', ' ', $value);

        return trim(is_string($value) ? preg_replace('/\s+/', ' ', $value) ?? '' : '');
    }

    private function site_secret(): string
    {
        if (function_exists('wp_salt')) {
            return wp_salt('nonce');
        }

        return 'wmcp-agentops-unit-test-secret';
    }
}
