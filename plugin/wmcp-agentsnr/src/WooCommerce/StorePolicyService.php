<?php

/**
 * Published structured store-policy facts.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

use WPWebMCP\AgentSNR\WebMCP\ToolException;

final class StorePolicyService
{
    private const OPTION = 'wmcp_agentsnr_store_policies';

    /**
     * @param array<string, mixed> $input Validated tool input.
     * @return array{policies: list<array<string, mixed>>}
     */
    public function get(array $input, ?array $product = null): array
    {
        $requested = (string) ($input['policy_type'] ?? 'all');
        $stored    = get_option(self::OPTION, array());
        $stored    = is_array($stored) ? $stored : array();
        $types     = 'all' === $requested ? array('returns', 'shipping', 'warranty', 'privacy') : array($requested);
        $policies  = array();

        foreach ($types as $type) {
            $value = $stored[$type] ?? null;
            if (! is_array($value)) {
                continue;
            }

            $policy = $this->normalize($type, $value);
            if (null !== $policy) {
                if ('returns' === $type && null !== $product && isset($product['return_days'])) {
                    $policy['product_return_days'] = $product['return_days'];
                }
                $policies[] = $policy;
            }
        }

        if (array() === $policies) {
            throw new ToolException('policy_not_found', 'No published policy facts were found for that request.', 404);
        }

        return array('policies' => $policies);
    }

    /**
     * @param array<string, mixed> $value Stored policy.
     * @return array<string, mixed>|null
     */
    private function normalize(string $type, array $value): ?array
    {
        $page_id = isset($value['page_id']) ? (int) $value['page_id'] : 0;
        if (0 < $page_id && 'publish' !== get_post_status($page_id)) {
            return null;
        }

        $url = $page_id ? get_permalink($page_id) : (isset($value['url']) ? esc_url_raw((string) $value['url']) : '');
        if (! is_string($url) || '' === $url) {
            return null;
        }

        $facts = isset($value['facts']) && is_array($value['facts']) ? $value['facts'] : array();
        $facts = array_intersect_key(
            $facts,
            array_flip(array('return_days', 'longer_marked_window_applies', 'final_sale_excluded', 'shipping_regions', 'dispatch_days', 'warranty_days'))
        );

        return array(
            'type'               => $type,
            'facts'              => $facts,
            'effective_date'     => isset($value['effective_date']) ? sanitize_text_field((string) $value['effective_date']) : '',
            'url'                => $url,
            'evidence_excerpt'   => $this->excerpt((string) ($value['evidence_excerpt'] ?? '')),
            'product_exceptions' => $this->exceptions($value['product_exceptions'] ?? array()),
        );
    }

    /**
     * @param mixed $values Stored product exceptions.
     * @return list<array<string, mixed>>
     */
    private function exceptions($values): array
    {
        if (! is_array($values)) {
            return array();
        }

        $result = array();
        foreach (array_slice($values, 0, 10) as $value) {
            if (! is_array($value)) {
                continue;
            }

            $exception = array();
            if (isset($value['product_id'])) {
                $exception['product_id'] = absint($value['product_id']);
            }
            if (isset($value['return_days']) && is_numeric($value['return_days'])) {
                $exception['return_days'] = max(0, (int) $value['return_days']);
            }
            if (isset($value['note'])) {
                $exception['note'] = $this->excerpt((string) $value['note']);
            }
            if (array() !== $exception) {
                $result[] = $exception;
            }
        }

        return $result;
    }

    private function excerpt(string $value): string
    {
        $value = wp_strip_all_tags(strip_shortcodes($value), true);
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return function_exists('mb_substr') ? mb_substr($value, 0, 300, 'UTF-8') : substr($value, 0, 300);
    }
}
